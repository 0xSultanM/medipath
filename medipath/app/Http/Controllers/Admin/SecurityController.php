<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Admin profile, password and session security (admin/14). */
class SecurityController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'device' => $this->deviceLabel((string) $session->user_agent),
                'ip' => $session->ip_address,
                'lastActive' => now()->setTimestamp((int) $session->last_activity)->toIso8601String(),
                'current' => $session->id === $currentId,
            ])->values()->all();

        if (! collect($sessions)->contains(fn (array $s): bool => $s['current'])) {
            array_unshift($sessions, [
                'id' => $currentId,
                'device' => $this->deviceLabel((string) $request->userAgent()),
                'ip' => $request->ip(),
                'lastActive' => now()->toIso8601String(),
                'current' => true,
            ]);
        }

        return Inertia::render('Admin/Security', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roleLabel' => $user->role?->label(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            ],
            'security' => [
                'idleMinutes' => $this->settings->adminIdleMinutes(),
                'sessionVersion' => (int) $user->session_version,
                'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
            ],
            'sessions' => $sessions,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'idle_minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'phone' => $validated['phone'] ?? null,
        ])->save();

        $this->settings->set('admin_idle_minutes', (int) $validated['idle_minutes'], $user);

        $this->activity->record(ActivityActions::USER_UPDATED, $user, [
            'event' => 'admin_profile_updated',
            'idle_minutes' => (int) $validated['idle_minutes'],
        ], $user);

        return back()->with('success', 'Profile and idle-timeout window saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $version = $user->session_version + 1;
        $user->forceFill([
            'password' => $validated['password'],
            'password_changed_at' => now(),
            'session_version' => $version,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        $request->session()->put('auth.password_changed_at', $user->password_changed_at?->getTimestamp());
        $request->session()->put('auth.session_version', $version);

        $this->activity->record(ActivityActions::SECURITY_PASSWORD_CHANGED, $user, [
            'sessions_revoked' => true,
        ], $user);

        return back()->with('success', 'Password changed. Other sessions were revoked.');
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $version = $user->session_version + 1;

        $user->forceFill(['session_version' => $version])->save();
        $deleted = DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        $request->session()->put('auth.session_version', $version);
        $request->session()->put('auth.password_changed_at', $user->password_changed_at?->getTimestamp());

        $this->activity->record(ActivityActions::SECURITY_SESSIONS_REVOKED, $user, [
            'deleted_rows' => $deleted,
            'scope' => 'all_other_sessions',
        ], $user);

        return back()->with('success', 'All other sessions were revoked. Redis-backed sessions are invalidated by the version bump.');
    }

    private function deviceLabel(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Mac') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        return $browser.' · '.$os;
    }
}