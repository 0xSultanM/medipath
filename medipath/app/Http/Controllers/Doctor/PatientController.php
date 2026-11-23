<?php

namespace App\Http\Controllers\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\CurrencyFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Doctor's own patient list and read-only clinical chart (PLAN Sprint 6.6). */
class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $q = trim((string) $request->query('q', ''));
        $lastVisit = (string) $request->query('last_visit', 'any');
        $allergyOnly = $request->boolean('allergies');

        $patients = Patient::query()
            ->where(fn ($scope) => $scope
                ->whereHas('appointments', fn ($query) => $query->where('doctor_id', $doctor->id))
                ->orWhereHas('prescriptions', fn ($query) => $query->where('doctor_id', $doctor->id)))
            ->with(['user:id,name,email,status', 'allergies'])
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when($lastVisit !== 'any', function ($query) use ($lastVisit, $doctor) {
                $days = $lastVisit === '7' ? 7 : 30;
                $query->whereHas('appointments', fn ($appointment) => $appointment
                    ->where('doctor_id', $doctor->id)
                    ->whereDate('date', '>=', today()->subDays($days)));
            })
            ->when($allergyOnly, fn ($query) => $query->whereHas('allergies'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $rows = $patients->getCollection()->map(function (Patient $patient) use ($doctor): array {
            $last = Appointment::query()->where('patient_id', $patient->id)->where('doctor_id', $doctor->id)
                ->where('status', AppointmentStatus::Completed)->orderByDesc('date')->first();
            $next = Appointment::query()->where('patient_id', $patient->id)->where('doctor_id', $doctor->id)
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
                ->whereDate('date', '>=', today())->orderBy('date')->first();
            $severe = $patient->allergies->filter(fn ($allergy) => $allergy->isSevere());

            return [
                'id' => $patient->id,
                'code' => $patient->patient_code,
                'name' => $patient->name,
                'initials' => $patient->initials,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'condition' => $patient->condition,
                'allergyCount' => $patient->allergies->count(),
                'severeAllergyCount' => $severe->count(),
                'allergySubstances' => $severe->pluck('substance')->values()->all(),
                'lastVisit' => $last?->date?->toDateString(),
                'nextVisit' => $next ? $next->date?->toDateString().' '.substr((string) $next->start_time, 0, 5) : null,
            ];
        });

        $forced = $this->state($request);
        $state = $forced ?? ($patients->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Doctor/Patients', [
            'patients' => $rows,
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'from' => $patients->firstItem(),
                'to' => $patients->lastItem(),
            ],
            'filters' => ['q' => $q, 'last_visit' => $lastVisit, 'allergies' => $allergyOnly],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, Patient $patient, CurrencyFormatter $money): Response
    {
        $doctor = $this->doctor($request);
        abort_unless($this->hasAccess($doctor, $patient), 403);

        $tab = in_array($request->query('tab'), ['prescriptions', 'appointments', 'billing'], true) ? $request->query('tab') : 'prescriptions';

        $prescriptions = Prescription::query()->where('patient_id', $patient->id)->with(['doctor.user', 'items.medicine'])->latest()->limit(20)->get();
        $appointments = Appointment::query()->where('patient_id', $patient->id)->with('doctor.user')->orderByDesc('date')->limit(20)->get();
        $invoices = Invoice::query()->where('patient_id', $patient->id)->latest()->limit(20)->get();

        $forced = $this->state($request);
        $state = $forced ?? 'success';

        return Inertia::render('Doctor/PatientDetail', [
            'tab' => $tab,
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->patient_code,
                'name' => $patient->name,
                'initials' => $patient->initials,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'condition' => $patient->condition,
                'address' => $patient->address,
                'walkInCode' => $patient->walk_in_code,
            ],
            'allergies' => $patient->allergies()->get()->map(fn ($allergy): array => [
                'id' => $allergy->id,
                'substance' => $allergy->substance,
                'type' => $allergy->allergy_type,
                'severity' => $allergy->severity?->value,
                'severityLabel' => $allergy->severity?->label(),
                'reactionNotes' => $allergy->reaction_notes,
            ])->values(),
            'medications' => $patient->medications()->where('is_active', true)->get()->map(fn ($medication): array => [
                'id' => $medication->id,
                'name' => $medication->medicine_name,
                'dosage' => $medication->dosage,
                'frequency' => $medication->frequency,
            ])->values(),
            'prescriptions' => $prescriptions->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'doctor' => $rx->doctor?->user?->name,
                'status' => $rx->status?->value,
                'statusLabel' => $rx->status?->label(),
                'hasOverride' => $rx->hasOverride(),
                'overrideJustification' => $rx->override_justification,
                'itemNames' => $rx->items->map(fn ($item) => ($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? ''))->values()->all(),
                'createdAt' => $rx->created_at?->toIso8601String(),
            ])->values(),
            'appointments' => $appointments->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'time' => substr((string) $appointment->start_time, 0, 5),
                'doctor' => $appointment->doctor?->user?->name,
                'status' => $appointment->status?->value,
                'statusLabel' => $appointment->status?->label(),
                'reason' => $appointment->visit_reason,
            ])->values(),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'netFormatted' => $money->format((float) $invoice->net_total, $invoice->currency_code),
                'status' => $invoice->payment_status?->value,
                'statusLabel' => $invoice->payment_status?->label(),
                'createdAt' => $invoice->created_at?->toIso8601String(),
            ])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    private function hasAccess(Doctor $doctor, Patient $patient): bool
    {
        return Appointment::query()->where('patient_id', $patient->id)->where('doctor_id', $doctor->id)->exists()
            || Prescription::query()->where('patient_id', $patient->id)->where('doctor_id', $doctor->id)->exists();
    }

    private function doctor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'No doctor profile is linked to this account.');

        return $doctor;
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}