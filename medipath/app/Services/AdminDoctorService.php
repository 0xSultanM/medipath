<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Admin doctor CRUD with soft archive + dependency evidence. */
class AdminDoctorService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Doctor
    {
        $doctor = DB::transaction(function () use ($data, $actor): Doctor {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower((string) $data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Doctor,
                'status' => AccountStatus::Approved,
                'verification_status' => VerificationStatus::Pending,
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

            $doctor = Doctor::query()->create([
                'user_id' => $user->id,
                'doctor_code' => 'DOC-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'specialty_id' => (int) $data['specialty_id'],
                'experience_years' => (int) ($data['experience_years'] ?? 0),
                'license_number' => $data['license_number'],
                'license_authority' => $data['license_authority'],
                'fee' => $data['fee'] ?? 0,
                'bio' => $data['bio'] ?? null,
                'clinic_name' => $data['clinic_name'] ?? $this->settings->clinicName(),
                'clinic_address' => $data['clinic_address'] ?? null,
                'room' => $data['room'] ?? null,
                'verification_status' => VerificationStatus::Pending,
            ]);

            $this->activity->record(ActivityActions::USER_CREATED, $user, [
                'role' => 'doctor',
                'source' => 'admin_doctor_crud',
                'doctor_id' => $doctor->id,
            ], $actor);

            return $doctor;
        });

        $this->notifications->send(
            $doctor->user,
            'account.doctor_created',
            'Your doctor profile was created',
            'An administrator created your doctor profile. Credential verification is pending; booking access opens after verification.',
            'account',
            ['doctor_id' => $doctor->id],
            '/login',
        );

        return $doctor->fresh(['user', 'specialty']);
    }

    /** @param array<string, mixed> $data */
    public function update(Doctor $doctor, array $data, User $actor): Doctor
    {
        DB::transaction(function () use ($doctor, $data, $actor): void {
            $user = $doctor->user;
            $user->forceFill([
                'name' => $data['name'],
                'email' => Str::lower((string) $data['email']),
                'phone' => $data['phone'] ?? null,
            ])->save();

            $doctor->forceFill([
                'specialty_id' => (int) $data['specialty_id'],
                'experience_years' => (int) ($data['experience_years'] ?? 0),
                'license_number' => $data['license_number'],
                'license_authority' => $data['license_authority'],
                'fee' => $data['fee'] ?? 0,
                'bio' => $data['bio'] ?? null,
                'clinic_name' => $data['clinic_name'] ?? $this->settings->clinicName(),
                'clinic_address' => $data['clinic_address'] ?? null,
                'room' => $data['room'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ])->save();

            $this->activity->record(ActivityActions::USER_UPDATED, $doctor, [
                'source' => 'admin_doctor_crud',
                'doctor_id' => $doctor->id,
            ], $actor);
        });

        return $doctor->fresh(['user', 'specialty']);
    }

    /** @return array<string, int> */
    public function dependencyCounts(Doctor $doctor): array
    {
        return [
            'appointments' => Appointment::query()->where('doctor_id', $doctor->id)->count(),
            'prescriptions' => Prescription::query()->where('doctor_id', $doctor->id)->count(),
            'reviews' => Review::query()->where('doctor_id', $doctor->id)->count(),
            'schedules' => DoctorSchedule::query()->where('doctor_id', $doctor->id)->count(),
        ];
    }

    public function archive(Doctor $doctor, string $confirmName, User $actor): Doctor
    {
        if (trim($confirmName) !== $doctor->user->name) {
            throw ValidationException::withMessages([
                'confirm_name' => 'Type the doctor name exactly to confirm archive.',
            ]);
        }

        DB::transaction(function () use ($doctor, $actor): void {
            $doctor->forceFill(['archived_at' => now()])->save();
            $doctor->user->forceFill([
                'status' => AccountStatus::Archived,
                'archived_at' => now(),
                'session_version' => $doctor->user->session_version + 1,
            ])->save();

            DB::table('sessions')->where('user_id', $doctor->user_id)->delete();

            $this->activity->record(ActivityActions::USER_ARCHIVED, $doctor, [
                'source' => 'admin_doctor_crud',
                'dependency_counts' => $this->dependencyCounts($doctor),
            ], $actor);
        });

        return $doctor->fresh(['user', 'specialty']);
    }

    public function restore(Doctor $doctor, User $actor): Doctor
    {
        DB::transaction(function () use ($doctor, $actor): void {
            $doctor->forceFill(['archived_at' => null])->save();
            $doctor->user->forceFill([
                'status' => AccountStatus::Approved,
                'archived_at' => null,
                'session_version' => $doctor->user->session_version + 1,
            ])->save();

            $this->activity->record(ActivityActions::USER_RESTORED, $doctor, [
                'event' => 'restored',
                'source' => 'admin_doctor_crud',
            ], $actor);
        });

        return $doctor->fresh(['user', 'specialty']);
    }
}