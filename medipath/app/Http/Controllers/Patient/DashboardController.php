<?php

namespace App\Http\Controllers\Patient;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Models\SymptomCheckLog;
use App\Services\CurrencyFormatter;
use App\Services\DoctorAvailabilityService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Patient dashboard (patient/01). */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CurrencyFormatter $money,
        DoctorAvailabilityService $availability,
        SettingsService $settings,
    ): Response {
        $user = $request->user();
        $patient = $user->patient;

        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $next = Appointment::query()
            ->with(['doctor.user', 'doctor.specialty'])
            ->where('patient_id', $patient->id)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $upcoming = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
            ->whereDate('date', '>=', today())
            ->count();

        $prescriptions = Prescription::query()->where('patient_id', $patient->id);
        $pendingRx = (clone $prescriptions)->where('status', PrescriptionStatus::PendingDispense->value)->count();

        $outstanding = Invoice::query()->where('patient_id', $patient->id)->where('payment_status', PaymentStatus::Pending->value);
        $outstandingTotal = (float) (clone $outstanding)->sum('net_total');
        $outstandingCount = (clone $outstanding)->count();

        $symptomChecks = SymptomCheckLog::query()->where('patient_id', $patient->id)->count();

        $forced = $this->state($request);
        $hasData = $upcoming > 0 || $prescriptions->count() > 0 || $outstandingCount > 0 || $symptomChecks > 0;
        $state = $forced ?? ($hasData ? 'success' : 'empty');

        $notifications = $user->notifications()->limit(6)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'category' => $n->category,
            'readAt' => $n->read_at?->toIso8601String(),
            'createdAt' => $n->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Patient/Dashboard', [
            'greetingName' => str($user->name)->before(' ')->toString(),
            'nextAppointment' => $next ? [
                'id' => $next->id,
                'code' => $next->appointment_code,
                'doctorName' => $next->doctor?->user?->name,
                'doctorCode' => $next->doctor?->doctor_code,
                'specialty' => $next->doctor?->specialty?->name,
                'date' => $next->date?->toDateString(),
                'time' => substr((string) $next->start_time, 0, 5),
                'room' => $next->doctor?->room,
                'clinic' => $next->doctor?->clinic_name,
                'status' => $next->status?->value,
                'statusLabel' => $next->status?->label(),
                'feeFormatted' => $money->format((float) ($next->doctor?->fee ?? 0)),
                'canCancel' => true,
                'canReschedule' => true,
            ] : null,
            'kpis' => [
                'upcomingVisits' => $upcoming,
                'prescriptions' => $prescriptions->count(),
                'pendingPrescriptions' => $pendingRx,
                'outstandingFormatted' => $money->format($outstandingTotal),
                'outstandingCount' => $outstandingCount,
                'symptomChecks' => $symptomChecks,
            ],
            'notifications' => $notifications,
            'settings' => [
                'cancellationCutoffHours' => $settings->cancellationCutoffHours(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}