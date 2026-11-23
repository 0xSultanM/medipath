<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Jobs\RegistrationReceived;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PendingApproval;
use App\Models\Pharmacist;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\ActivityActions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Atomic multi-role registration (PLAN Sprint 1.2). User + role profile +
 * credential storage + approval-queue item + audit entry commit together, then
 * a queued job notifies the applicant and the approval desk.
 */
class RegistrationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
        private readonly WalkInClaimService $walkInClaims,
    ) {}

    /** @param array<string, mixed> $data */
    public function register(array $data, ?UploadedFile $credential, Request $request): User
    {
        $role = UserRole::from((string) $data['role']);
        $credentialPath = null;

        if ($credential) {
            $credentialPath = $credential->store('registrations/'.$role->value, 'credentials');
        }

        try {
            $user = DB::transaction(function () use ($data, $role, $credentialPath): User {
                $pending = $role !== UserRole::Patient || ! $this->settings->patientAutoApprove();

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => Str::lower((string) $data['email']),
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'role' => $role,
                    'status' => $pending ? AccountStatus::Pending : AccountStatus::Approved,
                    'verification_status' => $role === UserRole::Patient
                        ? VerificationStatus::Verified
                        : VerificationStatus::Pending,
                    'email_verified_at' => now(),
                    'locale' => $this->settings->locale(),
                    'timezone' => $this->settings->timezone(),
                ]);

                UserPreference::query()->create([
                    'user_id' => $user->id,
                    'theme' => 'system',
                    'language' => $this->settings->locale(),
                    'timezone' => $this->settings->timezone(),
                    'date_format' => $this->settings->dateFormat(),
                ]);

                $queueItem = null;

                if ($role === UserRole::Patient) {
                    $patient = Patient::query()->create([
                        'user_id' => $user->id,
                        'patient_code' => 'PT-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'name' => $data['name'],
                        'phone' => $data['phone'] ?? null,
                        'age' => $data['age'] ?? null,
                        'gender' => $data['gender'] ?? null,
                        'condition' => $data['condition'] ?? null,
                        'is_walk_in' => false,
                    ]);

                    if (! empty($data['claim_code'])) {
                        $this->walkInClaims->claim($patient, (string) $data['claim_code'], $user);
                    }

                    if ($pending) {
                        $queueItem = $this->queueItem($user, 'patient', null, null);
                    }
                }

                if ($role === UserRole::Doctor) {
                    $specialty = Specialty::query()->findOrFail((int) $data['specialty_id']);

                    Doctor::query()->create([
                        'user_id' => $user->id,
                        'doctor_code' => 'DOC-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'specialty_id' => $specialty->id,
                        'experience_years' => (int) ($data['experience_years'] ?? 0),
                        'license_number' => $data['license_number'],
                        'license_authority' => $data['license_authority'],
                        'credential_path' => $credentialPath,
                        'fee' => $data['fee'] ?? 0,
                        'clinic_name' => $this->settings->clinicName(),
                        'verification_status' => VerificationStatus::Pending,
                    ]);

                    $queueItem = $this->queueItem($user, 'doctor', [
                        'license_number' => $data['license_number'],
                        'authority' => $data['license_authority'],
                        'specialty' => $specialty->name,
                        'experience_years' => (int) ($data['experience_years'] ?? 0),
                        'fee' => $data['fee'] ?? 0,
                    ], $credentialPath);
                }

                if ($role === UserRole::Pharmacist) {
                    Pharmacist::query()->create([
                        'user_id' => $user->id,
                        'pharmacist_code' => 'PHA-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'license_number' => $data['license_number'],
                        'license_authority' => $data['license_authority'],
                        'credential_path' => $credentialPath,
                        'pharmacy_name' => $this->settings->clinicName(),
                    ]);

                    $queueItem = $this->queueItem($user, 'pharmacist', [
                        'license_number' => $data['license_number'],
                        'authority' => $data['license_authority'],
                    ], $credentialPath);
                }

                $this->activity->record(ActivityActions::USER_CREATED, $user, [
                    'role' => $role->value,
                    'status' => $user->status->value,
                    'has_credential' => $credentialPath !== null,
                    'approval_queue_id' => $queueItem?->id,
                ], $user);

                return $user;
            });
        } catch (\Throwable $e) {
            if ($credentialPath) {
                Storage::disk('credentials')->delete($credentialPath);
            }

            throw $e;
        }

        RegistrationReceived::dispatch($user->id);

        return $user->fresh(['patient', 'doctor', 'pharmacist', 'preferences']);
    }

    private function queueItem(User $user, string $role, ?array $licenseDetails, ?string $credentialPath): PendingApproval
    {
        return PendingApproval::query()->create([
            'user_id' => $user->id,
            'requested_role' => $role,
            'license_details' => $licenseDetails,
            'credential_path' => $credentialPath,
            'status' => 'pending',
        ]);
    }
}
