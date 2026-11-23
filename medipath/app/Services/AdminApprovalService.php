<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Doctor;
use App\Models\PendingApproval;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Approval queue + doctor verification decisions (PLAN Sprints 2.4/2.5).
 * Every decision is atomic, writes audit + notification, and keeps a doctor
 * account gated until verification is confirmed.
 */
class AdminApprovalService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    public function approveSignup(PendingApproval $approval, User $actor): PendingApproval
    {
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This request has already been decided.']);
        }

        DB::transaction(function () use ($approval, $actor): void {
            $user = $approval->user;
            $role = UserRole::from($approval->requested_role);

            $user->forceFill([
                'status' => AccountStatus::Approved,
                'verification_status' => $role === UserRole::Doctor
                    ? VerificationStatus::Pending
                    : VerificationStatus::Verified,
                'session_version' => $user->session_version + 1,
            ])->save();

            if ($role === UserRole::Pharmacist) {
                $user->pharmacist?->forceFill([
                    'approved_at' => now(),
                    'approved_by' => $actor->id,
                ])->save();
            }

            if ($role === UserRole::Doctor) {
                $user->doctor?->forceFill([
                    'verification_status' => VerificationStatus::Pending,
                ])->save();
            }

            $approval->forceFill([
                'status' => 'approved',
                'reviewer_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->activity->record(ActivityActions::APPROVAL_APPROVED, $approval, [
                'user_id' => $user->id,
                'requested_role' => $approval->requested_role,
                'doctor_verification_pending' => $role === UserRole::Doctor,
            ], $actor);

            $message = $role === UserRole::Doctor
                ? 'Your account was approved. Credential verification is still required before booking access opens.'
                : 'Your account was approved. You can now sign in to your workspace.';

            $this->notifications->send(
                $user,
                'account.approved',
                'Account approved',
                $message,
                'account',
                ['role' => $role->value],
                $role === UserRole::Doctor ? '/pending' : '/login',
            );

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return $approval->fresh(['user', 'reviewer']);
    }

    public function rejectSignup(PendingApproval $approval, string $reason, User $actor): PendingApproval
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['review_reason' => 'Give a concise reason (at least 10 characters).']);
        }

        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This request has already been decided.']);
        }

        DB::transaction(function () use ($approval, $reason, $actor): void {
            $approval->user->forceFill([
                'status' => AccountStatus::Rejected,
                'session_version' => $approval->user->session_version + 1,
            ])->save();

            $approval->forceFill([
                'status' => 'rejected',
                'review_reason' => $reason,
                'reviewer_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            DB::table('sessions')->where('user_id', $approval->user_id)->delete();

            $this->activity->record(ActivityActions::APPROVAL_REJECTED, $approval, [
                'user_id' => $approval->user_id,
                'requested_role' => $approval->requested_role,
                'reason' => $reason,
            ], $actor);

            $this->notifications->send(
                $approval->user,
                'account.rejected',
                'Application not approved',
                "Your application was not approved. Reason: {$reason}",
                'account',
                [],
                '/pending',
            );
        });

        return $approval->fresh(['user', 'reviewer']);
    }

    public function verifyDoctor(Doctor $doctor, string $decision, string $reason, User $actor): Doctor
    {
        $decision = in_array($decision, ['approve', 'resubmit', 'revoke'], true) ? $decision : 'resubmit';
        $reason = trim($reason);

        if ($decision !== 'approve' && mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => 'A reason of at least 10 characters is required.']);
        }

        DB::transaction(function () use ($doctor, $decision, $reason, $actor): void {
            $user = $doctor->user;

            if ($decision === 'approve') {
                $doctor->forceFill([
                    'verification_status' => VerificationStatus::Verified,
                    'verified_at' => now(),
                    'verified_by' => $actor->id,
                    'review_reason' => null,
                    'archived_at' => null,
                ])->save();

                $user->forceFill([
                    'status' => AccountStatus::Approved,
                    'verification_status' => VerificationStatus::Verified,
                ])->save();

                $approval = PendingApproval::query()
                    ->where('user_id', $user->id)
                    ->where('requested_role', 'doctor')
                    ->where('status', 'pending')
                    ->latest()
                    ->first();

                $approval?->forceFill([
                    'status' => 'approved',
                    'reviewer_id' => $actor->id,
                    'reviewed_at' => now(),
                ])->save();

                $this->activity->record(ActivityActions::DOCTOR_VERIFIED, $doctor, [
                    'user_id' => $user->id,
                    'reason' => $reason ?: null,
                ], $actor);

                $this->notifications->send(
                    $user,
                    'account.doctor_verified',
                    'License verified — booking access open',
                    'Your credentials were verified. You can now sign in to the doctor workspace and receive bookings.',
                    'account',
                    [],
                    '/doctor/dashboard',
                );
            }

            if ($decision === 'resubmit') {
                $doctor->forceFill([
                    'verification_status' => VerificationStatus::Resubmission,
                    'review_reason' => $reason,
                ])->save();

                $user->forceFill([
                    'status' => AccountStatus::Pending,
                    'verification_status' => VerificationStatus::Resubmission,
                    'session_version' => $user->session_version + 1,
                ])->save();

                PendingApproval::query()->updateOrCreate(
                    ['user_id' => $user->id, 'status' => 'pending'],
                    [
                        'requested_role' => 'doctor',
                        'status' => 'pending',
                        'review_reason' => $reason,
                    ],
                );

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $this->activity->record(ActivityActions::DOCTOR_RESUBMISSION_REQUESTED, $doctor, [
                    'user_id' => $user->id,
                    'reason' => $reason,
                ], $actor);

                $this->notifications->send(
                    $user,
                    'account.doctor_resubmission',
                    'Credential resubmission requested',
                    "Please resubmit your license. Reason: {$reason}",
                    'account',
                    [],
                    '/pending',
                );
            }

            if ($decision === 'revoke') {
                $doctor->forceFill([
                    'verification_status' => VerificationStatus::Revoked,
                    'review_reason' => $reason,
                ])->save();

                $user->forceFill([
                    'verification_status' => VerificationStatus::Revoked,
                    'session_version' => $user->session_version + 1,
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $this->activity->record(ActivityActions::DOCTOR_VERIFICATION_REVOKED, $doctor, [
                    'user_id' => $user->id,
                    'reason' => $reason,
                ], $actor);

                $this->notifications->send(
                    $user,
                    'account.doctor_revoked',
                    'Doctor verification revoked',
                    "Your license verification was revoked. Reason: {$reason}",
                    'account',
                    [],
                    '/pending',
                );
            }
        });

        return $doctor->fresh(['user', 'specialty']);
    }

    public function credentialPath(PendingApproval $approval): ?string
    {
        return $approval->credential_path;
    }

    public function storeCredentialIfMissing(?string $path, string $contents): ?string
    {
        if (! $path) {
            return null;
        }

        if (! Storage::disk('credentials')->exists($path)) {
            Storage::disk('credentials')->put($path, $contents);
        }

        return $path;
    }
}