<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin user governance (PLAN Sprint 2.3). Every destructive path protects the
 * acting admin, the final active admin, linked records and sessions; role
 * changes are atomic and stamp the immutable audit log.
 */
class AdminUserService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function createInternal(array $data, User $actor): User
    {
        $role = UserRole::from((string) $data['role']);

        if (! in_array($role, [UserRole::Patient, UserRole::Admin], true)) {
            throw ValidationException::withMessages([
                'role' => 'Doctor and pharmacist accounts use the credential/approval flow.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $role, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower((string) $data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => $role,
                'status' => AccountStatus::Approved,
                'verification_status' => VerificationStatus::Verified,
                'email_verified_at' => now(),
                'locale' => app(SettingsService::class)->locale(),
                'timezone' => app(SettingsService::class)->timezone(),
            ]);

            UserPreference::query()->create([
                'user_id' => $user->id,
                'theme' => 'system',
                'language' => app(SettingsService::class)->locale(),
                'timezone' => app(SettingsService::class)->timezone(),
            ]);

            if ($role === UserRole::Patient) {
                Patient::query()->create([
                    'user_id' => $user->id,
                    'patient_code' => 'PT-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'name' => $user->name,
                    'phone' => $user->phone,
                ]);
            }

            $this->activity->record(ActivityActions::USER_CREATED, $user, [
                'role' => $role->value,
                'source' => 'admin_internal',
            ], $actor);

            return $user;
        });

        $this->notifications->send(
            $user,
            'account.created',
            'Your MediPath account is ready',
            'An administrator created your account. Sign in with the temporary password provided by the clinic.',
            'account',
            [],
            '/login',
        );

        return $user->fresh();
    }

    /** @param array<string, mixed> $data */
    public function updateContact(User $target, array $data, User $actor): User
    {
        $target->forceFill([
            'name' => $data['name'],
            'email' => Str::lower((string) $data['email']),
            'phone' => $data['phone'] ?? null,
        ])->save();

        $this->activity->record(ActivityActions::USER_UPDATED, $target, [
            'fields' => array_keys($data),
        ], $actor);

        return $target->fresh();
    }

    /** @param array<string, mixed> $data */
    public function changeRole(User $target, array $data, User $actor): User
    {
        $role = UserRole::from((string) $data['role']);
        $reason = trim((string) $data['reason']);
        $oldRole = $target->role;

        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if ($oldRole === $role) {
            throw ValidationException::withMessages(['role' => 'This user already has that role.']);
        }

        $this->protectFinalAdmin($target, 'demote');

        DB::transaction(function () use ($target, $role, $oldRole, $reason, $data, $actor): void {
            if ($oldRole === UserRole::Doctor && $role !== UserRole::Doctor) {
                $target->doctor?->forceFill(['archived_at' => now()])->save();
            }

            if ($oldRole === UserRole::Pharmacist && $role !== UserRole::Pharmacist) {
                $target->pharmacist?->forceFill(['archived_at' => now()])->save();
            }

            if ($oldRole === UserRole::Patient && $role !== UserRole::Patient) {
                $target->patient?->forceFill(['archived_at' => now()])->save();
            }

            if ($role === UserRole::Doctor) {
                $this->applyDoctorRole($target, $data);
            }

            if ($role === UserRole::Pharmacist) {
                $this->applyPharmacistRole($target, $data);
            }

            if ($role === UserRole::Patient) {
                $this->applyPatientRole($target);
            }

            $target->forceFill([
                'role' => $role,
                'status' => AccountStatus::Approved,
                'verification_status' => match ($role) {
                    UserRole::Doctor => VerificationStatus::Pending,
                    default => VerificationStatus::Verified,
                },
                'session_version' => $target->session_version + 1,
            ])->save();

            DB::table('sessions')->where('user_id', $target->id)->delete();

            $this->activity->record(ActivityActions::USER_ROLE_CHANGED, $target, [
                'old_role' => $oldRole?->value,
                'new_role' => $role->value,
                'reason' => $reason,
                'actor_id' => $actor->id,
            ], $actor);

            $this->notifications->send(
                $target,
                'account.role_changed',
                'Your role was updated',
                "Your role changed from {$oldRole?->label()} to {$role->label()}. Reason: {$reason}",
                'account',
                ['old_role' => $oldRole?->value, 'new_role' => $role->value],
                '/',
            );
        });

        return $target->fresh(['doctor', 'pharmacist', 'patient']);
    }

    public function suspend(User $target, string $reason, User $actor): User
    {
        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['reason' => 'You cannot suspend your own account.']);
        }

        $this->protectFinalAdmin($target, 'suspend');

        $target->forceFill([
            'status' => AccountStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'session_version' => $target->session_version + 1,
        ])->save();

        DB::table('sessions')->where('user_id', $target->id)->delete();

        $this->activity->record(ActivityActions::USER_SUSPENDED, $target, ['reason' => $reason], $actor);
        $this->notifications->send($target, 'account.suspended', 'Account suspended', "Your account was suspended. Reason: {$reason}", 'account');

        return $target->fresh();
    }

    public function restore(User $target, User $actor): User
    {
        $target->forceFill([
            'status' => AccountStatus::Approved,
            'suspended_at' => null,
            'suspension_reason' => null,
            'archived_at' => null,
            'session_version' => $target->session_version + 1,
        ])->save();

        $target->doctor?->forceFill(['archived_at' => null])->save();
        $target->patient?->forceFill(['archived_at' => null])->save();
        $target->pharmacist?->forceFill(['archived_at' => null])->save();

        $this->activity->record(ActivityActions::USER_RESTORED, $target, ['event' => 'restored'], $actor);
        $this->notifications->send($target, 'account.restored', 'Account restored', 'Your account access has been restored.', 'account', [], '/login');

        return $target->fresh();
    }

    public function archive(User $target, string $confirmName, User $actor): User
    {
        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['confirm_name' => 'You cannot archive your own account.']);
        }

        if (trim($confirmName) !== $target->name) {
            throw ValidationException::withMessages(['confirm_name' => 'Type the account name exactly to confirm archive.']);
        }

        $this->protectFinalAdmin($target, 'archive');

        DB::transaction(function () use ($target, $actor): void {
            $target->forceFill([
                'status' => AccountStatus::Archived,
                'archived_at' => now(),
                'session_version' => $target->session_version + 1,
            ])->save();

            $target->doctor?->forceFill(['archived_at' => now()])->save();
            $target->patient?->forceFill(['archived_at' => now()])->save();
            $target->pharmacist?->forceFill(['archived_at' => now()])->save();

            DB::table('sessions')->where('user_id', $target->id)->delete();

            $this->activity->record(ActivityActions::USER_ARCHIVED, $target, [
                'linked_records_preserved' => true,
            ], $actor);
        });

        return $target->fresh();
    }

    private function protectFinalAdmin(User $target, string $action): void
    {
        if ($target->role !== UserRole::Admin) {
            return;
        }

        $otherAdmins = User::query()
            ->where('role', UserRole::Admin)
            ->where('status', AccountStatus::Approved)
            ->whereKeyNot($target->id)
            ->exists();

        if (! $otherAdmins) {
            throw ValidationException::withMessages([
                'target' => "The final active admin cannot be {$action}d.",
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyDoctorRole(User $target, array $data): void
    {
        $existing = Doctor::query()->where('user_id', $target->id)->first();
        $license = trim((string) ($data['license_number'] ?? ''));
        $authority = trim((string) ($data['license_authority'] ?? ''));

        if ($license === '' || $authority === '') {
            throw ValidationException::withMessages(['license_number' => 'A license number and authority are required for the doctor role.']);
        }

        $duplicate = Doctor::query()->where('license_number', $license)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['license_number' => 'That license is already linked to another doctor.']);
        }

        $specialty = Specialty::query()->findOrFail((int) ($data['specialty_id'] ?? 0));

        $attributes = [
            'doctor_code' => $existing?->doctor_code ?? 'DOC-'.str_pad((string) $target->id, 5, '0', STR_PAD_LEFT),
            'specialty_id' => $specialty->id,
            'experience_years' => (int) ($data['experience_years'] ?? 0),
            'license_number' => $license,
            'license_authority' => $authority,
            'fee' => $data['fee'] ?? 0,
            'clinic_name' => app(SettingsService::class)->clinicName(),
            'verification_status' => VerificationStatus::Pending,
            'archived_at' => null,
        ];

        if ($existing) {
            $existing->forceFill($attributes)->save();
        } else {
            Doctor::query()->create(['user_id' => $target->id, ...$attributes]);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyPharmacistRole(User $target, array $data): void
    {
        $existing = Pharmacist::query()->where('user_id', $target->id)->first();
        $license = trim((string) ($data['license_number'] ?? ''));
        $authority = trim((string) ($data['license_authority'] ?? ''));

        if ($license === '' || $authority === '') {
            throw ValidationException::withMessages(['license_number' => 'A pharmacy license and authority are required.']);
        }

        $duplicate = Pharmacist::query()->where('license_number', $license)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['license_number' => 'That pharmacy license is already linked to another pharmacist.']);
        }

        $attributes = [
            'pharmacist_code' => $existing?->pharmacist_code ?? 'PHA-'.str_pad((string) $target->id, 5, '0', STR_PAD_LEFT),
            'license_number' => $license,
            'license_authority' => $authority,
            'pharmacy_name' => app(SettingsService::class)->clinicName(),
            'approved_at' => now(),
            'archived_at' => null,
        ];

        if ($existing) {
            $existing->forceFill($attributes)->save();
        } else {
            Pharmacist::query()->create(['user_id' => $target->id, ...$attributes]);
        }
    }

    private function applyPatientRole(User $target): void
    {
        $existing = $target->patient;

        if ($existing) {
            $existing->forceFill(['archived_at' => null])->save();

            return;
        }

        Patient::query()->create([
            'user_id' => $target->id,
            'patient_code' => 'PT-'.str_pad((string) $target->id, 5, '0', STR_PAD_LEFT),
            'name' => $target->name,
            'phone' => $target->phone,
        ]);
    }
}