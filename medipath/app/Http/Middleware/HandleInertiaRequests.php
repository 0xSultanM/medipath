<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\EmergencyContact;
use App\Services\NotificationService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $themePreference = $user
            ? ($user->preferences?->theme ?? $this->settings->themeDefault())
            : ($request->cookie('mp-theme') ?: $this->settings->themeDefault());

        // Server renders the last explicit mode; the client resolves "system"
        // with prefers-color-scheme and keeps data-bs-theme in sync (G7).
        $theme = in_array($themePreference, ['light', 'dark'], true) ? $themePreference : 'light';

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'roleLabel' => $user->role?->label(),
                    'portal' => $user->role?->portal(),
                    'initials' => $user->initials,
                    'status' => $user->status?->value,
                    'statusLabel' => $user->status?->label(),
                    'verificationStatus' => $user->verification_status?->value,
                    'verificationLabel' => $user->verification_status?->label(),
                    'avatarUrl' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
                    'dashboardUrl' => route($user->dashboard_route),
                    'theme' => $user->preferences?->theme ?? 'system',
                    'unreadNotifications' => $this->notifications->unreadCount($user),
                ] : null,
            ],
            'ui' => [
                'theme' => $theme,
                'themePreference' => $themePreference,
                'reduceMotion' => (bool) ($user?->preferences?->reduce_motion ?? false),
                'appName' => 'MediPath',
                // ?state=skeleton|empty|error|success makes every data view's
                // variant demonstrable without code changes (G3).
                'initialState' => in_array($request->query('state'), ['success', 'skeleton', 'empty', 'error'], true)
                    ? $request->query('state')
                    : 'success',
            ],
            'settings' => $this->settings->toSharedArray(),
            'labels' => [
                'roles' => collect(config('medipath.roles'))->map(fn ($r) => $r['label'])->all(),
                'account' => collect(config('medipath.account_statuses'))->map(fn ($s) => $s['label'])->all(),
                'verification' => collect(config('medipath.verification_statuses'))->map(fn ($s) => $s['label'])->all(),
                'appointment' => collect(config('medipath.appointment_statuses'))->map(fn ($s) => $s['label'])->all(),
                'prescription' => collect(config('medipath.prescription_statuses'))->map(fn ($s) => $s['label'])->all(),
                'payment' => collect(config('medipath.payment_statuses'))->map(fn ($s) => $s['label'])->all(),
                'moderation' => collect(config('medipath.moderation_statuses'))->map(fn ($s) => $s['label'])->all(),
                'stock' => collect(config('medipath.stock_statuses'))->map(fn ($s) => $s['label'])->all(),
                'expiry' => collect(config('medipath.expiry_statuses'))->map(fn ($s) => $s['label'])->all(),
                'interaction' => collect(config('medipath.interaction_severities'))->map(fn ($s) => $s['label'])->all(),
                'allergy' => collect(config('medipath.allergy_severities'))->map(fn ($s) => $s['label'])->all(),
                'clinical' => ['emergency' => 'Emergency red flag'],
            ],
            'tones' => [
                'account' => collect(config('medipath.account_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'verification' => collect(config('medipath.verification_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'appointment' => collect(config('medipath.appointment_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'prescription' => collect(config('medipath.prescription_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'payment' => collect(config('medipath.payment_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'moderation' => collect(config('medipath.moderation_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'stock' => collect(config('medipath.stock_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'expiry' => collect(config('medipath.expiry_statuses'))->map(fn ($s) => $s['tone'])->all(),
                'interaction' => collect(config('medipath.interaction_severities'))->map(fn ($s) => $s['tone'])->all(),
                'allergy' => collect(config('medipath.allergy_severities'))->map(fn ($s) => $s['tone'])->all(),
                'clinical' => ['emergency' => 'emergency'],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'demoResetLink' => fn () => $request->session()->get('demo_reset_link'),
            ],
            'hotlines' => fn () => Cache::remember('emergency.hotlines', now()->addMinutes(10), function () {
                return EmergencyContact::query()->active()->get()->map(fn (EmergencyContact $contact) => [
                    'key' => $contact->key,
                    'label' => $contact->label,
                    'number' => $contact->number,
                    'tel' => $contact->telHref(),
                    'icon' => $contact->icon,
                ])->all();
            }),
            'notificationsFeed' => fn () => $user ? $this->notifications->recent($user, 8)
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'category' => $n->category,
                    'actionUrl' => $n->action_url,
                    'readAt' => $n->read_at?->toIso8601String(),
                    'createdAt' => $n->created_at?->toIso8601String(),
                ])->all() : [],
            'csrfToken' => csrf_token(),
        ];
    }

    /** Inertia can render role options from DB-independent config labels. */
    public function roleOptions(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()])->all();
    }
}
