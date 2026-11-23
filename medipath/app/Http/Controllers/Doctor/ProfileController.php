<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Review;
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

/** Doctor profile, settings, reviews and security (PLAN Sprint 6.1). */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    public function reviews(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $reviews = Review::query()
            ->with(['patient:id,name', 'appointment:id,appointment_code,date'])
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->get();

        $approved = $reviews->where('moderation_status', 'approved');
        $distribution = collect(range(1, 5))->mapWithKeys(fn (int $star) => [$star => $approved->where('rating', $star)->count()]);

        $forced = $this->state($request);
        $state = $forced ?? ($reviews->isEmpty() ? 'empty' : 'success');

        return Inertia::render('Doctor/Reviews', [
            'summary' => [
                'average' => $approved->isEmpty() ? null : round((float) $approved->avg('rating'), 1),
                'approved' => $approved->count(),
                'pending' => $reviews->where('moderation_status', 'pending')->count(),
                'rejected' => $reviews->where('moderation_status', 'rejected')->count(),
                'distribution' => $distribution,
            ],
            'reviews' => $reviews->map(fn (Review $review): array => [
                'id' => $review->id,
                'patientName' => $review->patient?->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->moderation_status?->value,
                'statusLabel' => $review->moderation_status?->label(),
                'moderationReason' => $review->moderation_reason,
                'appointmentCode' => $review->appointment?->appointment_code,
                'createdAt' => $review->created_at?->toIso8601String(),
            ])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function settings(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $user = $request->user();
        $prefs = $user->preferences;

        return Inertia::render('Doctor/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'doctorCode' => $doctor->doctor_code,
                'specialty' => $doctor->specialty?->name,
                'licenseNumber' => $doctor->license_number,
                'licenseAuthority' => $doctor->license_authority,
                'experienceYears' => $doctor->experience_years,
                'fee' => (string) $doctor->fee,
                'followUpFee' => (string) ($doctor->follow_up_fee ?? ''),
                'bio' => $doctor->bio,
                'clinicName' => $doctor->clinic_name,
                'clinicAddress' => $doctor->clinic_address,
                'room' => $doctor->room,
                'verified' => $doctor->isVerified(),
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
        $doctor = $this->doctor($request);
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'follow_up_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'clinic_name' => ['nullable', 'string', 'max:160'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($user, $doctor, $validated): void {
            $user->forceFill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $doctor->forceFill([
                'fee' => $validated['fee'],
                'follow_up_fee' => $validated['follow_up_fee'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'clinic_name' => $validated['clinic_name'] ?? null,
                'clinic_address' => $validated['clinic_address'] ?? null,
                'room' => $validated['room'] ?? null,
            ])->save();

            $this->activity->record(ActivityActions::DOCTOR_PROFILE_UPDATED, $doctor, [
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

        $this->activity->record(ActivityActions::DOCTOR_PASSWORD_CHANGED, $user, ['sessions_revoked' => true], $user);

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

    private function doctor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'No doctor profile is linked to this account.');

        return $doctor;
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}