<?php

namespace App\Jobs;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Queued registration notification + admin approval-queue alert (PLAN 1.2). */
class RegistrationReceived implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        if ($user->status === AccountStatus::Pending) {
            $notifications->send(
                $user,
                'account.registration_received',
                $user->role === UserRole::Patient ? 'Registration received' : 'Application received',
                $user->role === UserRole::Patient
                    ? 'Your account is queued for clinic approval. You can track the status on the approval screen.'
                    : 'Your credentials are with the clinic administrator for review. You can track the status on the approval screen.',
                'account',
                ['role' => $user->role->value],
                '/pending',
            );

            User::query()
                ->where('role', UserRole::Admin)
                ->where('status', AccountStatus::Approved)
                ->each(fn (User $admin) => $notifications->send(
                    $admin,
                    'approval.pending',
                    'New sign-up awaiting review',
                    "{$user->name} applied as {$user->role->label()}.",
                    'account',
                    ['user_id' => $user->id, 'role' => $user->role->value],
                    '/admin/approvals',
                ));
        } else {
            $notifications->send(
                $user,
                'account.welcome',
                'Welcome to MediPath',
                'Your patient portal is active. Keep your allergy list up to date for safer prescribing.',
                'account',
                [],
                '/patient/dashboard',
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Registration notification failed', [
            'user_id' => $this->userId,
            'message' => $exception->getMessage(),
        ]);
    }
}
