<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Global appointment oversight (PLAN Sprint 3.1). */
class AdminAppointmentService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Give a concise reason of at least 10 characters.',
            ]);
        }

        if (! $appointment->status instanceof AppointmentStatus || ! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true)) {
            throw ValidationException::withMessages([
                'appointment' => 'Only pending or accepted appointments can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $reason, $actor): Appointment {
            $appointment->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ])->save();

            $this->activity->record(ActivityActions::APPOINTMENT_CANCELLED, $appointment, [
                'appointment_code' => $appointment->appointment_code,
                'reason' => $reason,
                'cancelled_by_admin' => $actor->id,
                'slot_released' => true,
            ], $actor);

            $appointment->loadMissing(['patient.user', 'doctor.user']);

            if ($appointment->patient?->user) {
                $this->notifications->send(
                    $appointment->patient->user,
                    'appointment.cancelled_admin',
                    'Appointment cancelled by the clinic',
                    "Your appointment {$appointment->appointment_code} was cancelled. Reason: {$reason}",
                    'appointment',
                    ['appointment_id' => $appointment->id],
                    '/patient/appointments',
                );
            }

            if ($appointment->doctor?->user) {
                $this->notifications->send(
                    $appointment->doctor->user,
                    'appointment.cancelled_admin',
                    'Appointment cancelled by administration',
                    "Appointment {$appointment->appointment_code} was cancelled on the clinic's behalf. Reason: {$reason}",
                    'appointment',
                    ['appointment_id' => $appointment->id],
                    '/doctor/requests',
                );
            }

            return $appointment->fresh();
        });
    }
}