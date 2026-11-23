<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Patient booking, cancellation and rescheduling (PLAN Sprints 4.5/4.6). */
class PatientBookingService
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array{date:string,time:string,visit_reason:string} $data */
    public function book(Patient $patient, Doctor $doctor, array $data, User $actor): Appointment
    {
        $this->assertSlotOpen($doctor, $data['date'], $data['time']);

        try {
            $appointment = DB::transaction(function () use ($patient, $doctor, $data, $actor): Appointment {
                $appointment = Appointment::query()->create([
                    'appointment_code' => $this->code(),
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'date' => $data['date'],
                    'start_time' => $data['time'].':00',
                    'end_time' => Carbon::parse($data['date'].' '.$data['time'])->addMinutes(30)->format('H:i:s'),
                    'status' => AppointmentStatus::Pending,
                    'visit_reason' => trim($data['visit_reason']),
                    'created_by' => $actor->id,
                    'is_walk_in' => false,
                ]);

                $this->activity->record(ActivityActions::APPOINTMENT_BOOKED, $appointment, [
                    'appointment_code' => $appointment->appointment_code,
                    'doctor_id' => $doctor->id,
                    'date' => $data['date'],
                    'time' => $data['time'],
                ], $actor);

                return $appointment;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'slot' => 'That slot was just booked. Nothing was charged or created — choose another time.',
                ]);
            }

            throw $exception;
        }

        $appointment->loadMissing(['doctor.user', 'patient.user']);
        if ($appointment->doctor?->user) {
            $this->notifications->send(
                $appointment->doctor->user,
                'appointment.requested',
                'New appointment request',
                "{$patient->name} requested {$appointment->appointment_code} on ".Carbon::parse($appointment->date)->format('d M Y').' at '.substr((string) $appointment->start_time, 0, 5).'.',
                'appointment',
                ['appointment_id' => $appointment->id],
                '/doctor/requests',
            );
        }
        if ($patient->user) {
            $this->notifications->send(
                $patient->user,
                'appointment.requested',
                'Appointment request sent',
                "Your request {$appointment->appointment_code} is waiting for the doctor to accept.",
                'appointment',
                ['appointment_id' => $appointment->id],
                '/patient/appointments',
            );
        }

        return $appointment->fresh();
    }

    public function cancel(Patient $patient, Appointment $appointment, string $reason, User $actor): Appointment
    {
        $this->assertOwnership($patient, $appointment);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a short reason for cancelling.']);
        }

        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true)) {
            throw ValidationException::withMessages(['appointment' => 'Only pending or accepted appointments can be cancelled.']);
        }

        if ($appointment->status === AppointmentStatus::Accepted) {
            $this->assertBeforeCutoff($appointment, 'Cancellations inside the clinic cutoff must go through the clinic desk.');
        }

        $appointment->forceFill([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ])->save();

        $this->activity->record(ActivityActions::APPOINTMENT_CANCELLED_PATIENT, $appointment, [
            'appointment_code' => $appointment->appointment_code,
            'reason' => $reason,
            'slot_released' => true,
        ], $actor);

        $appointment->loadMissing('doctor.user');
        if ($appointment->doctor?->user) {
            $this->notifications->send(
                $appointment->doctor->user,
                'appointment.cancelled_patient',
                'Appointment cancelled by patient',
                "{$appointment->appointment_code} was cancelled. Reason: {$reason}",
                'appointment',
                ['appointment_id' => $appointment->id],
                '/doctor/requests',
            );
        }

        return $appointment->fresh();
    }

    /** @param array{date:string,time:string} $data */
    public function reschedule(Patient $patient, Appointment $appointment, array $data, User $actor): Appointment
    {
        $this->assertOwnership($patient, $appointment);

        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true)) {
            throw ValidationException::withMessages(['appointment' => 'Only pending or accepted appointments can be rescheduled.']);
        }

        if ($appointment->status === AppointmentStatus::Accepted) {
            $this->assertBeforeCutoff($appointment, 'Reschedule requests inside the clinic cutoff must go through the clinic desk.');
        }

        $appointment->loadMissing('doctor');
        $doctor = $appointment->doctor;
        $this->assertSlotOpen($doctor, $data['date'], $data['time'], $appointment->id);

        $previous = [
            'date' => $appointment->date?->toDateString(),
            'time' => substr((string) $appointment->start_time, 0, 5),
        ];

        try {
            DB::transaction(function () use ($appointment, $data, $previous, $actor): void {
                $appointment->forceFill([
                    'date' => $data['date'],
                    'start_time' => $data['time'].':00',
                    'end_time' => Carbon::parse($data['date'].' '.$data['time'])->addMinutes(30)->format('H:i:s'),
                    'status' => AppointmentStatus::Pending,
                    'accepted_at' => null,
                    'reschedule_count' => $appointment->reschedule_count + 1,
                ])->save();

                $this->activity->record(ActivityActions::APPOINTMENT_RESCHEDULED, $appointment, [
                    'appointment_code' => $appointment->appointment_code,
                    'previous' => $previous,
                    'new' => ['date' => $data['date'], 'time' => $data['time']],
                ], $actor);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'slot' => 'That slot was just booked. Your original appointment is unchanged — choose another time.',
                ]);
            }

            throw $exception;
        }

        $appointment->loadMissing(['doctor.user', 'patient.user']);
        if ($appointment->doctor?->user) {
            $this->notifications->send(
                $appointment->doctor->user,
                'appointment.rescheduled',
                'Appointment rescheduled',
                "{$appointment->appointment_code} moved to ".Carbon::parse($appointment->date)->format('d M Y').' at '.substr((string) $appointment->start_time, 0, 5).'. Please re-accept.',
                'appointment',
                ['appointment_id' => $appointment->id],
                '/doctor/requests',
            );
        }

        return $appointment->fresh();
    }

    private function assertSlotOpen(Doctor $doctor, string $date, string $time, ?int $ignoreAppointmentId = null): void
    {
        $day = collect($this->availability->forDoctor($doctor, $date, 1))->first();

        if (! $day || ! in_array($time, $day['open_times'], true)) {
            if ($ignoreAppointmentId) {
                $current = Appointment::query()->whereKey($ignoreAppointmentId)->first();
                if ($current && $current->date?->toDateString() === $date && substr((string) $current->start_time, 0, 5) === $time) {
                    return;
                }
            }

            throw ValidationException::withMessages([
                'slot' => 'That slot is no longer available. Availability is server-authoritative — choose an open time.',
            ]);
        }
    }

    private function assertOwnership(Patient $patient, Appointment $appointment): void
    {
        if ((int) $appointment->patient_id !== (int) $patient->id) {
            abort(403);
        }
    }

    private function assertBeforeCutoff(Appointment $appointment, string $message): void
    {
        $startsAt = $appointment->starts_at;
        if ($startsAt && $startsAt->lt(now()->addHours($this->settings->cancellationCutoffHours()))) {
            throw ValidationException::withMessages(['appointment' => $message]);
        }
    }

    private function code(): string
    {
        return 'APT-'.now()->format('ymd').strtoupper(Str::random(4));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}