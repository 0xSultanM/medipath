<?php

namespace App\Http\Controllers\Patient;

use App\Enums\AppointmentStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use App\Services\ActivityLogService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Patient review submission for completed visits (PLAN Sprint 4.10). */
class ReviewController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $patient = $this->patient($request);

        $reviewable = Appointment::query()
            ->with(['doctor.user', 'doctor.specialty'])
            ->where('patient_id', $patient->id)
            ->where('status', AppointmentStatus::Completed)
            ->whereDoesntHave('review')
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $submitted = Review::query()
            ->with(['doctor.user', 'doctor.specialty', 'appointment:id,appointment_code,date'])
            ->where('patient_id', $patient->id)
            ->latest()
            ->get();

        $selectedId = (int) $request->query('appointment', 0);
        $selected = $reviewable->firstWhere('id', $selectedId) ?? $reviewable->first();

        $forced = $this->state($request);
        $state = $forced ?? (($reviewable->count() + $submitted->count()) === 0 ? 'empty' : 'success');

        return Inertia::render('Patient/Reviews', [
            'reviewable' => $reviewable->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'doctorName' => $appointment->doctor?->user?->name,
                'doctorCode' => $appointment->doctor?->doctor_code,
                'specialty' => $appointment->doctor?->specialty?->name,
            ])->values(),
            'submitted' => $submitted->map(fn (Review $review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->moderation_status?->value,
                'statusLabel' => $review->moderation_status?->label(),
                'moderationReason' => $review->moderation_reason,
                'doctorName' => $review->doctor?->user?->name,
                'specialty' => $review->doctor?->specialty?->name,
                'appointmentCode' => $review->appointment?->appointment_code,
                'appointmentDate' => $review->appointment?->date?->toDateString(),
                'createdAt' => $review->created_at?->toIso8601String(),
            ])->values(),
            'selectedAppointmentId' => $selected?->id,
            'selectedAppointment' => $selected ? [
                'id' => $selected->id,
                'code' => $selected->appointment_code,
                'doctorName' => $selected->doctor?->user?->name,
                'specialty' => $selected->doctor?->specialty?->name,
                'date' => $selected->date?->toDateString(),
            ] : null,
            'counts' => [
                'awaitingReview' => $reviewable->count(),
                'submitted' => $submitted->count(),
                'published' => $submitted->where('moderation_status', ModerationStatus::Approved)->count(),
                'pendingModeration' => $submitted->where('moderation_status', ModerationStatus::Pending)->count(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $this->patient($request);
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'rating' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment = Appointment::query()->where('patient_id', $patient->id)->findOrFail((int) $validated['appointment_id']);

        if ($appointment->status !== AppointmentStatus::Completed) {
            throw ValidationException::withMessages(['appointment_id' => 'Only completed visits can be reviewed.']);
        }

        if (Review::query()->where('appointment_id', $appointment->id)->exists()) {
            throw ValidationException::withMessages(['appointment_id' => 'This visit already has a review.']);
        }

        $review = Review::query()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $appointment->doctor_id,
            'appointment_id' => $appointment->id,
            'rating' => (int) $validated['rating'],
            'comment' => isset($validated['comment']) ? trim((string) $validated['comment']) : null,
            'moderation_status' => ModerationStatus::Pending,
        ]);

        $this->activity->record(ActivityActions::REVIEW_SUBMITTED, $review, [
            'rating' => $review->rating,
            'appointment_id' => $appointment->id,
        ], $request->user());

        return back()->with('success', 'Review submitted. It will appear publicly after moderation.');
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