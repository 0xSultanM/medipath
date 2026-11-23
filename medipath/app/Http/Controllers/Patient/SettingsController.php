<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
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

/** Patient profile, password and preferences (PLAN Sprint 4.2). */
class SettingsController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $patient = $user->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $prefs = $user->preferences;

        return Inertia::render('Patient/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'bloodGroup' => $patient->blood_group,
                'condition' => $patient->condition,
                'address' => $patient->address,
                'patientCode' => $patient->patient_code,
                'walkInCode' => $patient->walk_in_code,
                'emailVerified' => $user->email_verified_at !== null,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            ],
            'preferences' => [
                'theme' => $prefs?->theme ?? 'system',
                'language' => $prefs?->language ?? $this->settings->locale(),
                'timezone' => $prefs?->timezone ?? $this->settings->timezone(),
                'dateFormat' => $prefs?->date_format ?? $this->settings->dateFormat(),
                'notificationPrefs' => $prefs?->notification_prefs ?? ['inapp' => true, 'email' => true, 'sms' => false],
                'reduceMotion' => (bool) ($prefs?->reduce_motion ?? false),
            ],
            'options' => [
                'genders' => config('medipath.registration.genders'),
                'locales' => config('medipath.locale.supported'),
                'timezones' => ['Asia/Dhaka', 'UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York'],
                'dateFormats' => array_keys(config('medipath.locale.date_formats')),
            ],
            'security' => [
                'sessionVersion' => (int) $user->session_version,
                'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
            ],
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $patient = $user->patient;
        abort_unless($patient, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', Rule::in(config('medipath.registration.genders'))],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'condition' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $patient, $validated): void {
            $user->forceFill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $patient->forceFill([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'age' => $validated['age'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'blood_group' => $validated['blood_group'] ?? null,
                'condition' => $validated['condition'] ?? null,
                'address' => $validated['address'] ?? null,
            ])->save();

            $this->activity->record(ActivityActions::PATIENT_PROFILE_UPDATED, $patient, [
                'fields' => array_keys($validated),
            ], $user);
        });

        return back()->with('success', 'Profile saved. Your clinician-facing chart was updated too.');
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

        $this->activity->record(ActivityActions::PATIENT_PASSWORD_CHANGED, $user, ['sessions_revoked' => true], $user);

        return back()->with('success', 'Password changed. Other sessions were revoked.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'language' => ['required', Rule::in(config('medipath.locale.supported'))],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(array_keys(config('medipath.locale.date_formats')))],
            'inapp' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            'reduce_motion' => ['sometimes', 'boolean'],
        ]);

        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => $validated['theme'],
                'language' => $validated['language'],
                'timezone' => $validated['timezone'],
                'date_format' => $validated['date_format'],
                'notification_prefs' => [
                    'inapp' => (bool) ($validated['inapp'] ?? false),
                    'email' => (bool) ($validated['email'] ?? false),
                    'sms' => (bool) ($validated['sms'] ?? false),
                ],
                'reduce_motion' => (bool) ($validated['reduce_motion'] ?? false),
            ],
        );

        $this->activity->record(ActivityActions::PATIENT_PREFERENCES_UPDATED, $user, [
            'theme' => $validated['theme'],
            'language' => $validated['language'],
        ], $user);

        return back()->with('success', 'Preferences saved.');
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $version = $user->session_version + 1;
        $user->forceFill(['session_version' => $version])->save();

        $deleted = DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->put('auth.session_version', $version);
        $request->session()->put('auth.password_changed_at', $user->password_changed_at?->getTimestamp());

        $this->activity->record(ActivityActions::PATIENT_PASSWORD_CHANGED, $user, [
            'event' => 'sessions_revoked',
            'deleted_rows' => $deleted,
        ], $user);

        return back()->with('success', 'All other sessions were revoked.');
    }
}