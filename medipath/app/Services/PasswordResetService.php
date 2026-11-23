<?php

namespace App\Services;

use App\Mail\PasswordResetLink;
use App\Models\PasswordReset;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hashed, single-use password reset tokens with a 30-minute TTL (PLAN 1.3).
 * Raw tokens never touch the database; only SHA-256 hashes are stored.
 */
class PasswordResetService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /** @return array{token:string,url:string}|null */
    public function request(User $user, string $ip): ?array
    {
        PasswordReset::query()->where('email', $user->email)->whereNull('consumed_at')->delete();

        $token = Str::random(64);

        PasswordReset::query()->create([
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes((int) config('medipath.security.reset_token_ttl_minutes')),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        Mail::to($user)->queue(new PasswordResetLink($user, $token));

        $this->activity->record(ActivityActions::AUTH_PASSWORD_RESET, $user, [
            'event' => 'requested',
            'ttl_minutes' => config('medipath.security.reset_token_ttl_minutes'),
        ], $user);

        return ['token' => $token, 'url' => $url];
    }

    public function validToken(string $email, string $token): bool
    {
        return PasswordReset::query()
            ->where('email', Str::lower($email))
            ->where('token_hash', hash('sha256', $token))
            ->valid()
            ->exists();
    }

    public function reset(string $email, string $token, string $password): User
    {
        $email = Str::lower($email);

        $reset = PasswordReset::query()
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $token))
            ->valid()
            ->first();

        if (! $reset) {
            throw ValidationException::withMessages([
                'token' => 'This reset link expired or was already used. Request a fresh link.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'This reset link is no longer valid.',
            ]);
        }

        DB::transaction(function () use ($reset, $user, $password): void {
            $user->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'session_version' => $user->session_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            $reset->forceFill(['consumed_at' => now()])->save();

            // Database sessions are deleted immediately; Redis/file sessions are
            // invalidated by EnsureSessionFresh via password_changed_at.
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->activity->record(ActivityActions::AUTH_PASSWORD_RESET, $user, [
                'event' => 'completed',
                'sessions_revoked' => true,
            ], $user);
        });

        return $user->fresh();
    }
}
