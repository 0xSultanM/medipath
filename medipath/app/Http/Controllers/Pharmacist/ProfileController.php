<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\Controller;
use App\Models\Pharmacist;
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

/** Pharmacist profile, password and preferences (PLAN Sprint 8.1). */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    public function settings(Request $request): Response
    {
        $pharmacist = $this->pharmacist($request);
        $user = $request->user();
        $prefs = $user->preferences;

        return Inertia::render('Pharmacist/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'pharmacistCode' => $pharmacist->pharmacist_code,
                'licenseNumber' => $pharmacist->license_number,
                'licenseAuthority' => $pharmacist->license_authority,
                'pharmacyName' => $pharmacist->pharmacy_name,
                'approvedAt' => $pharmacist->approved_at?->toIso8601String(),
            ],
            'preferences' => [
                'theme' => $prefs?->theme ?? 'system',
                'language' => $prefs?->language ?? $this->settings->locale(),
                'timezone' => $prefs?->timezone ?? $this->settings->timezone(),
                'dateFormat' => $prefs?->date_format ?? $this->settings->dateFormat(),
                'reduceMotion' => (bool) ($prefs?->reduce_motion ?? false),
            ],
            'options' => [
                'locales' => config('medipath.locale.supported'),
                'timezones' => ['Asia/Dhaka', 'UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York'],
                'dateFormats' => array_keys(config('medipath.locale.date_formats')),
            ],
            'security' => ['sessionVersion' => (int) $user->session_version],
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $pharmacist = $this->pharmacist($request);
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'pharmacy_name' => ['nullable', 'string', 'max:160'],
        ]);

        DB::transaction(function () use ($user, $pharmacist, $validated): void {
            $user->forceFill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $pharmacist->forceFill([
                'pharmacy_name' => $validated['pharmacy_name'] ?? null,
            ])->save();

            $this->activity->record(ActivityActions::PHARMACIST_PROFILE_UPDATED, $pharmacist, [
                'fields' => array_keys($validated),
            ], $user);
        });

        return back()->with('success', 'Profile updated.');
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

        $this->activity->record(ActivityActions::PHARMACIST_PASSWORD_CHANGED, $user, ['sessions_revoked' => true], $user);

        return back()->with('success', 'Password changed and other sessions revoked.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'language' => ['required', Rule::in(config('medipath.locale.supported'))],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(array_keys(config('medipath.locale.date_formats')))],
            'reduce_motion' => ['sometimes', 'boolean'],
        ]);

        $request->user()->preferences()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'theme' => $validated['theme'],
                'language' => $validated['language'],
                'timezone' => $validated['timezone'],
                'date_format' => $validated['date_format'],
                'reduce_motion' => (bool) ($validated['reduce_motion'] ?? false),
            ],
        );

        return back()->with('success', 'Preferences saved.');
    }

    private function pharmacist(Request $request): Pharmacist
    {
        $pharmacist = $request->user()->pharmacist;
        abort_unless($pharmacist, 403, 'No pharmacist profile is linked to this account.');

        return $pharmacist;
    }
}