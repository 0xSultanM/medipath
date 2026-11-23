<?php

namespace App\Http\Controllers\Patient;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\CurrencyFormatter;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorPresenter;
use App\Services\PatientBookingService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Patient appointment history + booking/reschedule/cancel (Sprints 4.5/4.6). */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly PatientBookingService $booking,
        private readonly DoctorAvailabilityService $availability,
        private readonly DoctorPresenter $presenter,
        private readonly CurrencyFormatter $money,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $patient = $this->patient($request);
        $tab = (string) $request->query('tab', 'all');
        $tab = in_array($tab, ['all', 'pending', 'accepted', 'completed', 'cancelled'], true) ? $tab : 'all';
        $q = trim((string) $request->query('q', ''));

        $base = Appointment::query()
            ->with(['doctor.user', 'doctor.specialty', 'review:id,appointment_id,moderation_status'])
            ->where('patient_id', $patient->id)
            ->when($tab !== 'all', fn ($query) => $query->where('status', $tab))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('appointment_code', 'like', $like)
                    ->orWhereHas('doctor.user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('doctor.specialty', fn ($s) => $s->where('name', 'like', $like)));
            });

        $rows = (clone $base)->orderByDesc('date')->orderByDesc('start_time')->paginate(10)->withQueryString();

        $counts = collect(['all', 'pending', 'accepted', 'completed', 'cancelled'])
            ->mapWithKeys(fn (string $key) => [$key => Appointment::query()
                ->where('patient_id', $patient->id)
                ->when($key !== 'all', fn ($query) => $query->where('status', $key))
                ->count()]);

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Patient/Appointments', [
            'appointments' => $rows->getCollection()->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'time' => substr((string) $appointment->start_time, 0, 5),
                'doctorName' => $appointment->doctor?->user?->name,
                'doctorCode' => $appointment->doctor?->doctor_code,
                'specialty' => $appointment->doctor?->specialty?->name,
                'room' => $appointment->doctor?->room,
                'status' => $appointment->status?->value,
                'statusLabel' => $appointment->status?->label(),
                'visitReason' => $appointment->visit_reason,
                'feeFormatted' => $this->money->format((float) ($appointment->doctor?->fee ?? 0)),
                'cancellationReason' => $appointment->cancellation_reason,
                'reviewStatus' => $appointment->review?->moderation_status?->value,
                'canCancel' => in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true),
                'canReschedule' => in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true),
                'canReview' => $appointment->status === AppointmentStatus::Completed && $appointment->review === null,
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'tab' => $tab,
            'counts' => $counts,
            'filters' => ['q' => $q],
            'statuses' => collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function create(Request $request, string $doctorCode): Response
    {
        $patient = $this->patient($request);
        $doctor = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->where('doctor_code', $doctorCode)
            ->with(['user', 'specialty'])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->firstOrFail();

        $reschedule = null;
        if ($request->filled('reschedule')) {
            $reschedule = Appointment::query()
                ->where('patient_id', $patient->id)
                ->whereKey((int) $request->query('reschedule'))
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
                ->first();
        }

        $days = $this->availability->forDoctor($doctor, null, 7);
        $selectedDate = (string) $request->query('date', '');
        if (! collect($days)->contains(fn (array $day) => $day['date'] === $selectedDate)) {
            $selectedDate = collect($days)->first(fn (array $day) => $day['open_slots'] > 0)['date'] ?? ($days[0]['date'] ?? today()->toDateString());
        }
        $selectedDay = collect($days)->firstWhere('date', $selectedDate);
        $openTimes = $selectedDay['open_times'] ?? [];
        $selectedTime = (string) $request->query('time', '');
        if ($selectedTime !== '' && ! in_array($selectedTime, $openTimes, true)) {
            $selectedTime = '';
        }

        return Inertia::render('Patient/Booking', [
            'doctor' => [
                ...$this->presenter->profile($doctor),
                'bookCode' => $doctor->doctor_code,
            ],
            'availability' => $days,
            'dates' => collect($days)->map(fn (array $day): array => [
                'date' => $day['date'],
                'day' => Carbon::parse($day['date'])->format('D'),
                'num' => Carbon::parse($day['date'])->format('j'),
                'isToday' => $day['is_today'],
                'isOff' => $day['status'] !== 'available' || $day['open_slots'] === 0,
            ])->values(),
            'slots' => collect($openTimes)->map(fn (string $time): array => [
                'time' => $time,
                'kind' => $time === $selectedTime ? 'selected' : 'open',
            ])->values(),
            'selectedDate' => $selectedDate,
            'selectedTime' => $selectedTime,
            'reschedule' => $reschedule ? [
                'id' => $reschedule->id,
                'code' => $reschedule->appointment_code,
                'visitReason' => $reschedule->visit_reason,
                'date' => $reschedule->date?->toDateString(),
                'time' => substr((string) $reschedule->start_time, 0, 5),
            ] : null,
            'cancellationCutoffHours' => $this->settings->cancellationCutoffHours(),
            'dataState' => 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $this->patient($request);
        $validated = $request->validate([
            'doctor_code' => ['required', 'string', 'exists:doctors,doctor_code'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'visit_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $doctor = Doctor::query()->verified()->where('doctor_code', $validated['doctor_code'])->firstOrFail();
        $appointment = $this->booking->book($patient, $doctor, $validated, $request->user());

        return redirect()->route('patient.appointments.index')
            ->with('success', "Appointment request {$appointment->appointment_code} sent. It appears as pending until the doctor accepts.");
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $patient = $this->patient($request);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $this->booking->reschedule($patient, $appointment, $validated, $request->user());

        return redirect()->route('patient.appointments.index')
            ->with('success', 'Appointment rescheduled. The doctor was notified and the old slot was released.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $patient = $this->patient($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->booking->cancel($patient, $appointment, (string) $validated['reason'], $request->user());

        return back()->with('success', 'Appointment cancelled and the slot released.');
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        return $patient;
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}