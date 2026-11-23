<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Doctor-side appointment request decisions (PLAN Sprint 6.3). */
class DoctorAppointmentService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    public function accept(Doctor $doctor, Appointment $appointment, User $actor): Appointment
    {
        $this->assertOwned($doctor, $appointment);

        if ($appointment->status !== AppointmentStatus::Pending) {
            throw ValidationException::withMessages(['appointment' => 'Only pending requests can be accepted.']);
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Accepted,
            'accepted_at' => now(),
        ])->save();

        $this->activity->record(ActivityActions::APPOINTMENT_ACCEPTED, $appointment, [
            'appointment_code' => $appointment->appointment_code,
        ], $actor);

        $this->notifyPatient($appointment, 'appointment.accepted', 'Appointment accepted', 'Your appointment '.$appointment->appointment_code.' was accepted. Please arrive 10 minutes early.', '/patient/appointments');

        return $appointment->fresh();
    }

    public function reject(Doctor $doctor, Appointment $appointment, string $reason, User $actor): Appointment
    {
        $this->assertOwned($doctor, $appointment);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a short reason (at least 5 characters).']);
        }

        if ($appointment->status !== AppointmentStatus::Pending) {
            throw ValidationException::withMessages(['appointment' => 'Only pending requests can be rejected.']);
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Rejected,
            'rejection_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ])->save();

        $this->activity->record(ActivityActions::APPOINTMENT_REJECTED, $appointment, [
            'appointment_code' => $appointment->appointment_code,
            'reason' => $reason,
            'slot_released' => true,
        ], $actor);

        $this->notifyPatient($appointment, 'appointment.rejected', 'Appointment not accepted', "Your request {$appointment->appointment_code} was not accepted. Reason: {$reason}", '/patient/appointments');

        return $appointment->fresh();
    }

    public function complete(Doctor $doctor, Appointment $appointment, User $actor): Appointment
    {
        $this->assertOwned($doctor, $appointment);

        if (! in_array($appointment->status, [AppointmentStatus::Accepted, AppointmentStatus::Pending], true)) {
            throw ValidationException::withMessages(['appointment' => 'Only active appointments can be completed.']);
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ])->save();

        $this->activity->record(ActivityActions::APPOINTMENT_COMPLETED, $appointment, [
            'appointment_code' => $appointment->appointment_code,
        ], $actor);

        $this->notifyPatient($appointment, 'appointment.completed', 'Visit completed', 'Your visit is complete. You can now view prescriptions and leave a review.', '/patient/appointments');

        return $appointment->fresh();
    }

    public function cancel(Doctor $doctor, Appointment $appointment, string $reason, User $actor): Appointment
    {
        $this->assertOwned($doctor, $appointment);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a short reason (at least 5 characters).']);
        }

        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true)) {
            throw ValidationException::withMessages(['appointment' => 'Only pending or accepted appointments can be cancelled.']);
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ])->save();

        $this->activity->record(ActivityActions::APPOINTMENT_CANCELLED_DOCTOR, $appointment, [
            'appointment_code' => $appointment->appointment_code,
            'reason' => $reason,
            'slot_released' => true,
        ], $actor);

        $this->notifyPatient($appointment, 'appointment.cancelled_doctor', 'Appointment cancelled by doctor', "Appointment {$appointment->appointment_code} was cancelled. Reason: {$reason}", '/patient/appointments');

        return $appointment->fresh();
    }

    private function assertOwned(Doctor $doctor, Appointment $appointment): void
    {
        if ((int) $appointment->doctor_id !== (int) $doctor->id) {
            abort(403);
        }
    }

    private function notifyPatient(Appointment $appointment, string $type, string $title, string $message, string $url): void
    {
        $appointment->loadMissing('patient.user');
        if ($appointment->patient?->user) {
            $this->notifications->send($appointment->patient->user, $type, $title, $message, 'appointment', ['appointment_id' => $appointment->id], $url);
        }
    }
}