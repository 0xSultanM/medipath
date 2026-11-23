<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use App\Services\CurrencyFormatter;
use App\Services\NotificationService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Local-only design-system gallery + shell preview.
 *
 * Purpose: make every Part 0 component's 4 states demonstrable without
 * inventing product screens that belong to later parts. Returns 404 outside
 * the `local` environment (asserted in tests).
 */
class DevDesignSystemController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CurrencyFormatter $money,
        private readonly NotificationService $notifications,
    ) {}

    public function gallery(Request $request): Response
    {
        abort_unless(app()->environment('local'), 404);

        $doctor = Doctor::query()->verified()->with(['user', 'specialty'])->first();
        $user = $request->user();

        return Inertia::render('Dev/DesignSystem', [
            'tokens' => [
                'currencySymbol' => $this->settings->currencySymbol(),
                'formattedFee' => $this->money->format($doctor?->fee ?? 1200),
                'formattedCompact' => $this->money->formatCompact(1_190_000),
                'formattedNegative' => $this->money->format(-1250.5),
            ],
            'sampleDoctor' => $doctor ? [
                'id' => $doctor->id,
                'name' => $doctor->user->name,
                'initials' => $doctor->user->initials,
                'specialty' => $doctor->specialty->name,
                'verified' => $doctor->isVerified(),
                'experienceYears' => $doctor->experience_years,
                'clinic' => $doctor->clinic_name,
                'rating' => $doctor->average_rating,
                'reviewCount' => $doctor->review_count,
                'feeFormatted' => $this->money->format($doctor->fee),
                'nextAvailable' => 'Today, 4:30 PM',
                'hasSlots' => true,
            ] : null,
            'sampleSlots' => [
                ['time' => '09:00', 'kind' => 'past'],
                ['time' => '09:30', 'kind' => 'booked'],
                ['time' => '10:00', 'kind' => 'open'],
                ['time' => '10:30', 'kind' => 'selected'],
                ['time' => '11:00', 'kind' => 'leave'],
                ['time' => '11:30', 'kind' => 'open'],
                ['time' => '12:00', 'kind' => 'open'],
                ['time' => '12:30', 'kind' => 'closed'],
            ],
            'sampleDates' => collect(range(0, 6))->map(fn (int $i) => [
                'date' => now()->addDays($i)->toDateString(),
                'day' => now()->addDays($i)->format('D'),
                'num' => now()->addDays($i)->format('j'),
                'isToday' => $i === 0,
                'isOff' => in_array($i, [4, 6], true),
            ])->all(),
            'notifications' => $user ? $this->notifications->recent($user, 4)->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'category' => $n->category,
                'readAt' => $n->read_at?->toIso8601String(),
                'createdAt' => $n->created_at?->toIso8601String(),
            ]) : [],
            'emergencyContacts' => EmergencyContact::query()->active()->get()->map(fn ($c) => [
                'key' => $c->key,
                'label' => $c->label,
                'number' => $c->number,
                'tel' => $c->telHref(),
                'icon' => $c->icon,
            ]),
            'emergencyRules' => EmergencyRule::query()->active()->get()->groupBy('category')->map(
                fn ($rules, $category) => [
                    'category' => $category,
                    'items' => $rules->map(fn ($r) => [
                        'id' => $r->id,
                        'title' => $r->title,
                        'directive' => $r->action_directive,
                    ])->values()->all(),
                ]
            )->values(),
        ]);
    }

    public function layout(string $role): Response
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless(in_array($role, ['admin', 'doctor', 'pharmacist', 'patient'], true), 404);

        return Inertia::render('Dev/LayoutPreview', [
            'previewRole' => $role,
        ]);
    }

    /**
     * Local-only responsive harness: renders the real app inside an iframe at an
     * exact CSS width so 360/768/1440 media queries execute faithfully.
     */
    public function frames(Request $request, int $width): View
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless(in_array($width, [360, 640, 768, 1024, 1440], true), 404);

        $path = (string) $request->query('path', '/');

        // Only allow same-origin absolute paths.
        abort_unless(str_starts_with($path, '/') && ! str_starts_with($path, '//'), 422);

        return view('dev.frames', [
            'width' => $width,
            'path' => $path,
            'scale' => min(1, 760 / $width),
        ]);
    }
}
