<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Atomic walk-in registration + claim code (PLAN Sprint 6.5). */
class WalkInRegistrationService
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
        private readonly CurrencyFormatter $money,
    ) {}

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function register(Doctor $doctor, array $data, User $actor): array
    {
        $date = (string) $data['date'];
        $time = (string) $data['time'];
        $day = collect($this->availability->forDoctor($doctor, $date, 1))->first();

        if (! $day || ! in_array($time, $day['open_times'], true)) {
            throw ValidationException::withMessages(['time' => 'That slot is no longer open. Pick another available time.']);
        }

        return DB::transaction(function () use ($doctor, $data, $actor, $date, $time): array {
            $claimCode = $this->claimCode();

            $user = User::query()->create([
                'name' => trim((string) $data['name']),
                'email' => Str::lower('walkin.'.Str::slug($claimCode).'@walkin.medipath.local'),
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(24),
                'role' => UserRole::Patient,
                'status' => AccountStatus::Approved,
                'verification_status' => VerificationStatus::Verified,
                'email_verified_at' => now(),
                'locale' => $this->settings->locale(),
                'timezone' => $this->settings->timezone(),
            ]);

            UserPreference::query()->create([
                'user_id' => $user->id,
                'theme' => 'system',
                'language' => $this->settings->locale(),
                'timezone' => $this->settings->timezone(),
            ]);

            $patient = Patient::query()->create([
                'user_id' => $user->id,
                'patient_code' => 'PT-W'.Str::upper(Str::random(5)),
                'name' => trim((string) $data['name']),
                'phone' => $data['phone'] ?? null,
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
                'condition' => $data['condition'] ?? null,
                'is_walk_in' => true,
                'walk_in_code' => $claimCode,
            ]);

            $appointment = Appointment::query()->create([
                'appointment_code' => 'APT-'.now()->format('ymd').Str::upper(Str::random(4)),
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'date' => $date,
                'start_time' => $time.':00',
                'end_time' => \Illuminate\Support\Carbon::parse($date.' '.$time)->addMinutes(30)->format('H:i:s'),
                'status' => AppointmentStatus::Accepted,
                'visit_reason' => trim((string) $data['condition'] ?: 'Walk-in consultation'),
                'created_by' => $actor->id,
                'accepted_at' => now(),
                'is_walk_in' => true,
            ]);

            $this->activity->record(ActivityActions::WALK_IN_REGISTERED, $patient, [
                'patient_code' => $patient->patient_code,
                'claim_code' => $claimCode,
                'appointment_code' => $appointment->appointment_code,
                'doctor_id' => $doctor->id,
            ], $actor);

            return [
                'claimCode' => $claimCode,
                'patientCode' => $patient->patient_code,
                'appointmentCode' => $appointment->appointment_code,
                'patientName' => $patient->name,
                'doctorName' => $doctor->user?->name,
                'date' => $date,
                'time' => $time,
                'feeFormatted' => $this->money->format((float) $doctor->fee),
            ];
        });
    }

    private function claimCode(): string
    {
        do {
            $code = 'WI-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (Patient::query()->where('walk_in_code', $code)->exists());

        return $code;
    }
}