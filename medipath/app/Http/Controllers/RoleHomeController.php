<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Part 0 role-shell placeholder. Each later phase replaces this page with the
 * designed dashboard for that role; the layout contract is already fixed.
 */
class RoleHomeController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();
        $money = app(CurrencyFormatter::class);

        return Inertia::render('Role/Home', [
            'role' => [
                'value' => $user->role->value,
                'label' => $user->role->label(),
                'portal' => $user->role->portal(),
            ],
            'currencyPreview' => [
                'standard' => $money->format(12500.5),
                'compact' => $money->formatCompact(1_190_000),
                'negative' => $money->format(-450),
                'currencyCode' => app(SettingsService::class)->currencyCode(),
            ],
            'checklist' => [
                'Shell renders at 360 / 768 / 1440',
                'Sidebar collapses to slide-over drawer below 992px',
                'Notification bell + aria-live panel',
                'Theme toggle persists via user_preferences',
                'Skip link and focus rings verified',
            ],
            'notificationPreview' => Notification::query()
                ->where('user_id', $user->id)
                ->latest()->limit(3)->get()
                ->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'category' => $n->category,
                    'createdAt' => $n->created_at?->toIso8601String(),
                    'readAt' => $n->read_at?->toIso8601String(),
                ]),
        ]);
    }
}
