<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Review;
use App\Services\ActivityLogService;
use App\Services\DoctorAppointmentService;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorScheduleService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Doctor dashboard, schedule and request queue (PLAN Sprints 6.2–6.4). */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly DoctorScheduleService $schedule,
        private readonly DoctorAppointmentService $appointments,
        private readonly DoctorAvailabilityService $availability,
        private readonly ActivityLogService $activity,
    ) {}

    public function dashboard(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $today = today();

        $todayAppointments = Appointment::query()
            ->with(['patient:id,patient_code,name', 'patient.user:id,name'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->get();

        $pending = Appointment::query()
            ->with(['patient:id,patient_code,name'])
            ->where('doctor_id', $doctor->id)
            ->where('status', 'pending')
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        $weekStart = now()->startOfWeek();
        $prescriptionsThisWeek = Prescription::query()->where('doctor_id', $doctor->id)->where('created_at', '>=', $weekStart)->count();
        $rating = Review::query()->where('doctor_id', $doctor->id)->where('moderation_status', 'approved')->avg('rating');
        $reviewCount = Review::query()->where('doctor_id', $doctor->id)->where('moderation_status', 'approved')->count();
        $nextLeave = DoctorLeave::query()->where('doctor_id', $doctor->id)->where('status', 'approved')->whereDate('end_date', '>=', $today)->orderBy('start_date')->first();

        $forced = $this->state($request);
        $state = $forced ?? ($todayAppointments->count() + $pending->count() > 0 ? 'success' : 'empty');

        return Inertia::render('Doctor/Dashboard', [
            'kpis' => [
                'todayTotal' => $todayAppointments->count(),
                'todayCompleted' => $todayAppointments->where('status', 'completed')->count(),
                'pendingRequests' => Appointment::query()->where('doctor_id', $doctor->id)->where('status', 'pending')->count(),
                'prescriptionsWeek' => $prescriptionsThisWeek,
                'averageRating' => $rating ? round((float) $rating, 1) : null,
                'reviewCount' => $reviewCount,
            ],
            'timeline' => $todayAppointments->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'start' => substr((string) $appointment->start_time, 0, 5),
                'end' => substr((string) $appointment->end_time, 0, 5),
                'patientId' => $appointment->patient_id,
                'patient' => $appointment->patient?->name,
                'patientCode' => $appointment->patient?->patient_code,
                'reason' => $appointment->visit_reason,
                'status' => $appointment->status?->value,
                'statusLabel' => $appointment->status?->label(),
                'isWalkIn' => $appointment->is_walk_in,
            ])->values(),
            'pending' => $pending->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'time' => substr((string) $appointment->start_time, 0, 5),
                'patientId' => $appointment->patient_id,
                'patient' => $appointment->patient?->name,
                'patientCode' => $appointment->patient?->patient_code,
                'reason' => $appointment->visit_reason,
                'requestedAt' => $appointment->created_at?->diffForHumans(),
            ])->values(),
            'nextLeave' => $nextLeave ? [
                'start' => $nextLeave->start_date?->toDateString(),
                'end' => $nextLeave->end_date?->toDateString(),
                'reason' => $nextLeave->reason,
            ] : null,
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function schedule(Request $request): Response
    {
        $doctor = $this->doctor($request);

        $blocks = DoctorSchedule::query()
            ->where('doctor_id', $doctor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $leaves = DoctorLeave::query()
            ->where('doctor_id', $doctor->id)
            ->where('status', 'approved')
            ->orderBy('start_date')
            ->get()
            ->map(fn (DoctorLeave $leave): array => [
                'id' => $leave->id,
                'start' => $leave->start_date?->toDateString(),
                'end' => $leave->end_date?->toDateString(),
                'reason' => $leave->reason,
                'impacted' => $this->schedule->impactedAppointments($leave),
            ]);

        $forced = $this->state($request);
        $state = $forced ?? ($blocks->count() > 0 ? 'success' : 'empty');

        return Inertia::render('Doctor/Schedule', [
            'blocks' => $blocks->map(fn (DoctorSchedule $block): array => [
                'id' => $block->id,
                'dayOfWeek' => $block->day_of_week,
                'dayLabel' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$block->day_of_week] ?? '—',
                'start' => substr((string) $block->start_time, 0, 5),
                'end' => substr((string) $block->end_time, 0, 5),
                'slotDuration' => $block->slot_duration,
                'breakStart' => $block->break_start ? substr((string) $block->break_start, 0, 5) : null,
                'breakEnd' => $block->break_end ? substr((string) $block->break_end, 0, 5) : null,
                'isActive' => $block->is_active,
            ])->values(),
            'leaves' => $leaves->values(),
            'weekdays' => collect(range(0, 6))->map(fn (int $day): array => [
                'value' => $day,
                'label' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day],
            ])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'slot_duration' => ['required', 'integer', Rule::in([10, 15, 20, 30, 45, 60])],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i'],
        ]);

        $this->schedule->addBlock($doctor, $validated, $request->user());

        return back()->with('success', 'Weekly block added. Patients can now book those slots.');
    }

    public function toggleBlock(Request $request, DoctorSchedule $block): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $this->schedule->toggleBlock($doctor, $block, $request->user());

        return back()->with('success', $block->fresh()->is_active ? 'Block enabled.' : 'Block disabled.');
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3', 'max:160'],
        ]);

        $result = $this->schedule->addLeave($doctor, $validated, $request->user());

        return back()->with('success', $result['affected'] > 0
            ? "Leave saved. {$result['affected']} booked appointment(s) were cancelled and the patients notified."
            : 'Leave saved. Affected slots are hidden from booking.');
    }

    public function cancelLeave(Request $request, DoctorLeave $leave): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $this->schedule->cancelLeave($doctor, $leave, $request->user());

        return back()->with('success', 'Leave cancelled. Slots reappear for booking.');
    }

    public function requests(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $tab = (string) $request->query('tab', 'pending');
        $tab = in_array($tab, ['pending', 'today', 'upcoming', 'completed', 'cancelled'], true) ? $tab : 'pending';

        $base = Appointment::query()
            ->with(['patient:id,patient_code,name'])
            ->where('doctor_id', $doctor->id);

        $rows = (clone $base)
            ->when($tab === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($tab === 'today', fn ($q) => $q->whereDate('date', today())->whereIn('status', ['accepted', 'pending', 'completed']))
            ->when($tab === 'upcoming', fn ($q) => $q->whereDate('date', '>', today())->whereIn('status', ['accepted', 'pending']))
            ->when($tab === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($tab === 'cancelled', fn ($q) => $q->whereIn('status', ['cancelled', 'rejected']))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(15)
            ->withQueryString();

        $counts = collect(['pending' => 'pending', 'today' => null, 'upcoming' => null, 'completed' => 'completed', 'cancelled' => null])
            ->map(function ($status, $key) use ($doctor): int {
                return match ($key) {
                    'today' => Appointment::query()->where('doctor_id', $doctor->id)->whereDate('date', today())->count(),
                    'upcoming' => Appointment::query()->where('doctor_id', $doctor->id)->whereDate('date', '>', today())->whereIn('status', ['accepted', 'pending'])->count(),
                    'cancelled' => Appointment::query()->where('doctor_id', $doctor->id)->whereIn('status', ['cancelled', 'rejected'])->count(),
                    default => Appointment::query()->where('doctor_id', $doctor->id)->where('status', $status)->count(),
                };
            });

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Doctor/Requests', [
            'tab' => $tab,
            'requests' => $rows->getCollection()->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'time' => substr((string) $appointment->start_time, 0, 5),
                'patientId' => $appointment->patient_id,
                'patient' => $appointment->patient?->name,
                'patientCode' => $appointment->patient?->patient_code,
                'reason' => $appointment->visit_reason,
                'status' => $appointment->status?->value,
                'statusLabel' => $appointment->status?->label(),
                'isWalkIn' => $appointment->is_walk_in,
                'rejectionReason' => $appointment->rejection_reason,
                'cancellationReason' => $appointment->cancellation_reason,
                'requestedAt' => $appointment->created_at?->diffForHumans(),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'counts' => $counts,
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function accept(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->accept($this->doctor($request), $appointment, $request->user());

        return back()->with('success', 'Appointment accepted. The patient was notified.');
    }

    public function reject(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->appointments->reject($this->doctor($request), $appointment, (string) $validated['reason'], $request->user());

        return back()->with('success', 'Request rejected and the slot released.');
    }

    public function complete(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->complete($this->doctor($request), $appointment, $request->user());

        return back()->with('success', 'Visit marked completed.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->appointments->cancel($this->doctor($request), $appointment, (string) $validated['reason'], $request->user());

        return back()->with('success', 'Appointment cancelled with notice and the slot released.');
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