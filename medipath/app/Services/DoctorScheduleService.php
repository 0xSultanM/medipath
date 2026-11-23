<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Doctor weekly availability and leave management (PLAN Sprint 6.2). */
class DoctorScheduleService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function addBlock(Doctor $doctor, array $data, User $actor): DoctorSchedule
    {
        $day = (int) $data['day_of_week'];
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];

        if ($end <= $start) {
            throw ValidationException::withMessages(['end_time' => 'The end time must be after the start time.']);
        }

        $overlap = DoctorSchedule::query()
            ->where('doctor_id', $doctor->id)
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_time' => 'That block overlaps an existing active block on this weekday.']);
        }

        $block = DoctorSchedule::query()->create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'slot_duration' => (int) ($data['slot_duration'] ?? 30),
            'break_start' => ($data['break_start'] ?? null) ?: null,
            'break_end' => ($data['break_end'] ?? null) ?: null,
            'is_active' => true,
        ]);

        $this->activity->record(ActivityActions::SCHEDULE_UPDATED, $block, [
            'event' => 'block_added',
            'day_of_week' => $day,
            'start' => $start,
            'end' => $end,
        ], $actor);

        return $block;
    }

    public function toggleBlock(Doctor $doctor, DoctorSchedule $block, User $actor): DoctorSchedule
    {
        $this->assertBlockOwned($doctor, $block);

        $block->forceFill(['is_active' => ! $block->is_active])->save();

        $this->activity->record(ActivityActions::SCHEDULE_UPDATED, $block, [
            'event' => $block->is_active ? 'block_enabled' : 'block_disabled',
            'day_of_week' => $block->day_of_week,
        ], $actor);

        return $block->fresh();
    }

    /** @param array<string, mixed> $data @return array{leave:DoctorLeave,affected:int} */
    public function addLeave(Doctor $doctor, array $data, User $actor): array
    {
        $start = (string) $data['start_date'];
        $end = (string) $data['end_date'];

        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }

        $overlap = DoctorLeave::query()
            ->where('doctor_id', $doctor->id)
            ->where('status', 'approved')
            ->overlapping($start, $end)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'That period overlaps an existing approved leave.']);
        }

        return DB::transaction(function () use ($doctor, $data, $actor, $start, $end): array {
            $leave = DoctorLeave::query()->create([
                'doctor_id' => $doctor->id,
                'start_date' => $start,
                'end_date' => $end,
                'reason' => trim((string) $data['reason']),
                'status' => 'approved',
                'created_by' => $actor->id,
            ]);

            $appointments = Appointment::query()
                ->with('patient.user')
                ->where('doctor_id', $doctor->id)
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
                ->whereDate('date', '>=', $start)
                ->whereDate('date', '<=', $end)
                ->get();

            foreach ($appointments as $appointment) {
                $appointment->forceFill([
                    'status' => AppointmentStatus::Cancelled,
                    'cancellation_reason' => 'Doctor unavailable — approved leave: '.$leave->reason,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                ])->save();

                if ($appointment->patient?->user) {
                    $this->notifications->send(
                        $appointment->patient->user,
                        'appointment.cancelled_leave',
                        'Appointment cancelled — doctor leave',
                        "Appointment {$appointment->appointment_code} was cancelled because the doctor is on approved leave. Please rebook another date.",
                        'appointment',
                        ['appointment_id' => $appointment->id],
                        '/patient/appointments',
                    );
                }
            }

            $this->activity->record(ActivityActions::LEAVE_UPDATED, $leave, [
                'event' => 'leave_created',
                'start' => $start,
                'end' => $end,
                'affected_appointments' => $appointments->count(),
            ], $actor);

            return ['leave' => $leave->fresh(), 'affected' => $appointments->count()];
        });
    }

    public function cancelLeave(Doctor $doctor, DoctorLeave $leave, User $actor): DoctorLeave
    {
        $this->assertLeaveOwned($doctor, $leave);

        $leave->forceFill(['status' => 'cancelled'])->save();

        $this->activity->record(ActivityActions::LEAVE_UPDATED, $leave, [
            'event' => 'leave_cancelled',
            'start' => $leave->start_date?->toDateString(),
            'end' => $leave->end_date?->toDateString(),
        ], $actor);

        return $leave->fresh();
    }

    public function impactedAppointments(DoctorLeave $leave): int
    {
        return Appointment::query()
            ->where('doctor_id', $leave->doctor_id)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Accepted])
            ->whereDate('date', '>=', $leave->start_date)
            ->whereDate('date', '<=', $leave->end_date)
            ->count();
    }

    private function assertBlockOwned(Doctor $doctor, DoctorSchedule $block): void
    {
        if ((int) $block->doctor_id !== (int) $doctor->id) {
            abort(403);
        }
    }

    private function assertLeaveOwned(Doctor $doctor, DoctorLeave $leave): void
    {
        if ((int) $leave->doctor_id !== (int) $doctor->id) {
            abort(403);
        }
    }
}