<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest authentication (PLAN Sprints 1.2/1.3). Pending and rejected applicants
 * may authenticate only to reach the holding screen; every role portal is
 * guarded by RoleGate until approval.
 */
class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            return redirect()->route(
                $user->status === AccountStatus::Approved ? $user->dashboard_route : 'pending.approval'
            );
        }

        return Inertia::render('Guest/Login', [
            'redirect' => $this->safeRedirect($request->query('redirect')),
            'demoAccounts' => app()->environment('local')
                ? User::query()
                    ->whereIn('email', [
                        'patient@medipath.test',
                        'ayesha.rahman@medipath.test',
                        'pharmacist@medipath.test',
                        'admin@medipath.test',
                        'tanvir.alam@medipath.test',
                    ])
                    ->orderBy('role')
                    ->get(['name', 'email', 'role', 'status'])
                    ->map(fn (User $u) => [
                        'name' => $u->name,
                        'email' => $u->email,
                        'roleLabel' => $u->role->label(),
                        'statusLabel' => $u->status->label(),
                    ])
                : [],
            'demoPassword' => app()->environment('local') ? config('medipath.demo.password') : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'redirect' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $key = $this->throttleKey($credentials['email'], $request);

        if (RateLimiter::tooManyAttempts($key, (int) config('medipath.security.login_max_attempts'))) {
            $seconds = RateLimiter::availableIn($key);

            $this->activity->record(ActivityActions::AUTH_LOGIN_FAILED, detail: [
                'email' => $credentials['email'],
                'reason' => 'throttled',
            ]);

            $message = "Too many sign-in attempts. Try again in {$seconds} seconds.";
            $error = ValidationException::withMessages(['email' => $message]);

            if ($request->expectsJson()) {
                throw $error->status(429);
            }

            return back()->withErrors(['email' => $message])->with('error', $message);
        }

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            (bool) ($credentials['remember'] ?? false),
        )) {
            RateLimiter::hit($key, (int) config('medipath.security.login_decay_seconds'));

            $this->activity->record(ActivityActions::AUTH_LOGIN_FAILED, detail: [
                'email' => $credentials['email'],
                'reason' => 'invalid_credentials',
            ]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->canAttemptLogin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->activity->record(ActivityActions::AUTH_LOGIN_FAILED, detail: [
                'email' => $credentials['email'],
                'reason' => 'account_not_active',
                'status' => $user->status?->value,
            ], actor: $user);

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact the clinic administrator.',
            ]);
        }

        $request->session()->regenerate();
        $this->markAuthenticatedSession($request, $user);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->activity->record(ActivityActions::AUTH_LOGIN, $user, actor: $user);

        if ($user->status !== AccountStatus::Approved) {
            return redirect()->route('pending.approval')
                ->with('info', 'Your account is awaiting a decision. Track the status on this page.');
        }

        $target = $this->safeRedirect($credentials['redirect'] ?? null);

        return $target
            ? redirect($target)
            : redirect()->intended(route($user->dashboard_route));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->activity->record(ActivityActions::AUTH_LOGOUT, $user, actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }

    public function markAuthenticatedSession(Request $request, User $user): void
    {
        $request->session()->put('auth.password_changed_at', $user->password_changed_at?->getTimestamp());
        $request->session()->put('auth.session_version', (int) $user->session_version);
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login|'.Str::lower($email).'|'.$request->ip();
    }

    private function safeRedirect(?string $target): ?string
    {
        if (! $target) {
            return null;
        }

        if (! str_starts_with($target, '/') || str_starts_with($target, '//') || str_starts_with($target, '/login')) {
            return null;
        }

        return $target;
    }
}
