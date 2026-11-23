<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Enums\PrescriptionStatus;
use App\Services\CurrencyFormatter;
use App\Services\PrescriptionSafetyService;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Prescription builder + live safety checks (PLAN Sprints 6.7/6.8). */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionSafetyService $safety) {}

    public function index(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $rows = Prescription::query()
            ->with(['patient:id,patient_code,name', 'items.medicine'])
            ->where('doctor_id', $doctor->id)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($inner) => $inner->where('prescription_code', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like)));
            })
            ->when(in_array($status, array_column(PrescriptionStatus::cases(), 'value'), true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Doctor/Prescriptions', [
            'prescriptions' => $rows->getCollection()->map(fn (Prescription $prescription): array => [
                'id' => $prescription->id,
                'code' => $prescription->prescription_code,
                'patientId' => $prescription->patient_id,
                'patientName' => $prescription->patient?->name,
                'patientCode' => $prescription->patient?->patient_code,
                'status' => $prescription->status?->value,
                'statusLabel' => $prescription->status?->label(),
                'hasOverride' => $prescription->hasOverride(),
                'itemNames' => $prescription->items->map(fn ($item) => ($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? ''))->values()->all(),
                'createdAt' => $prescription->created_at?->toIso8601String(),
                'pdfUrl' => route('doctor.prescriptions.pdf', $prescription),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'status' => $status],
            'statuses' => collect(PrescriptionStatus::cases())->map(fn (PrescriptionStatus $status) => ['value' => $status->value, 'label' => $status->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function create(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $patients = $this->treatedPatients($doctor);

        $selectedId = (int) $request->query('patient', 0);
        $patient = $selectedId > 0 ? $patients->firstWhere('id', $selectedId) : null;
        $appointment = null;

        if ($patient) {
            $appointment = Appointment::query()
                ->where('patient_id', $patient->id)
                ->where('doctor_id', $doctor->id)
                ->whereIn('status', ['accepted', 'completed', 'pending'])
                ->orderByDesc('date')
                ->first();
        }

        return Inertia::render('Doctor/PrescriptionBuilder', [
            'patients' => $patients->map(fn (Patient $p): array => [
                'id' => $p->id,
                'code' => $p->patient_code,
                'name' => $p->name,
            ])->values(),
            'selectedPatient' => $patient ? [
                'id' => $patient->id,
                'code' => $patient->patient_code,
                'name' => $patient->name,
                'initials' => $patient->initials,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'condition' => $patient->condition,
                'allergies' => $patient->allergies()->get()->map(fn ($allergy): array => [
                    'id' => $allergy->id,
                    'substance' => $allergy->substance,
                    'type' => $allergy->allergy_type,
                    'severity' => $allergy->severity?->value,
                    'severityLabel' => $allergy->severity?->label(),
                    'notes' => $allergy->reaction_notes,
                ])->values(),
                'medications' => $patient->medications()->where('is_active', true)->get()->map(fn ($med): array => [
                    'id' => $med->id,
                    'name' => $med->medicine_name,
                    'dosage' => $med->dosage,
                    'frequency' => $med->frequency,
                ])->values(),
            ] : null,
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'time' => substr((string) $appointment->start_time, 0, 5),
                'reason' => $appointment->visit_reason,
            ] : null,
            'dataState' => $patient ? 'success' : ($patients->isEmpty() ? 'empty' : 'success'),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function aiReview(Request $request): JsonResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['required', 'string', 'max:80'],
            'items.*.schedule' => ['required', 'string', 'max:120'],
            'items.*.duration' => ['required', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $patient = Patient::query()->findOrFail((int) $validated['patient_id']);
        $this->assertAccess($doctor, $patient);

        return response()->json($this->safety->aiReview($patient, $validated['items']))->header('Cache-Control', 'no-store');
    }

    public function safetyCheck(Request $request): JsonResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $patient = Patient::query()->findOrFail((int) $validated['patient_id']);
        $this->assertAccess($doctor, $patient);

        return response()->json($this->safety->evaluate($patient, $validated['items']))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['required', 'string', 'max:80'],
            'items.*.schedule' => ['required', 'string', 'max:120'],
            'items.*.duration' => ['required', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],
            'patient_notes' => ['nullable', 'string', 'max:2000'],
            'pharmacy_notes' => ['nullable', 'string', 'max:2000'],
            'override_justification' => ['nullable', 'string', 'max:2000'],
            'ai_requested' => ['sometimes', 'boolean'],
        ]);

        $patient = Patient::query()->findOrFail((int) $validated['patient_id']);
        $prescription = $this->safety->prescribe($doctor, $patient, $validated['items'], $validated, $request->user());

        return redirect()->route('doctor.patients.show', ['patient' => $patient->id, 'tab' => 'prescriptions'])
            ->with('success', "Prescription {$prescription->prescription_code} saved and sent to pharmacy.");
    }

    public function pdf(Request $request, Prescription $prescription, CurrencyFormatter $money, SettingsService $settings): HttpResponse
    {
        $doctor = $this->doctor($request);
        abort_unless((int) $prescription->doctor_id === (int) $doctor->id, 403);

        $prescription->load(['patient', 'doctor.user', 'doctor.specialty', 'items.medicine', 'invoice']);

        $pdf = Pdf::loadView('admin.pdf.prescription', [
            'prescription' => $prescription,
            'money' => $money,
            'clinic' => $settings->clinicName(),
            'header' => (string) $settings->get('pdf_header', $settings->clinicName()),
            'footer' => (string) $settings->get('pdf_footer', 'Generated electronically by MediPath.'),
        ])->setPaper('a4');

        return $pdf->download($prescription->prescription_code.'.pdf');
    }

    private function treatedPatients(Doctor $doctor)
    {
        return Patient::query()
            ->where(fn ($scope) => $scope
                ->whereHas('appointments', fn ($q) => $q->where('doctor_id', $doctor->id))
                ->orWhereHas('prescriptions', fn ($q) => $q->where('doctor_id', $doctor->id)))
            ->orderBy('name')
            ->get();
    }

    private function assertAccess(Doctor $doctor, Patient $patient): void
    {
        $allowed = Appointment::query()->where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->exists()
            || Prescription::query()->where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->exists();

        abort_unless($allowed, 403);
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }

    private function doctor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'No doctor profile is linked to this account.');

        return $doctor;
    }
}