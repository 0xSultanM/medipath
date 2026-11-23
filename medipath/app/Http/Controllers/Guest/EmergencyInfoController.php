<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\EmergencyBanner;
use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** Emergency information — deliberately public and offline-capable. */
class EmergencyInfoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $contacts = EmergencyContact::query()->active()->get();

        $groups = EmergencyRule::query()->active()->get()
            ->groupBy('category')
            ->map(fn ($rules, $category) => [
                'category' => $category,
                'items' => $rules->map(fn (EmergencyRule $rule) => [
                    'id' => $rule->id,
                    'title' => $rule->title,
                    'directive' => $rule->action_directive,
                ])->values(),
            ])
            ->values();

        $banner = EmergencyBanner::query()->live()->first();

        $lastSync = Cache::get('emergency.last_sync');
        Cache::put('emergency.last_sync', now(), now()->addHours(24));

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true) ? $forced : 'success';

        return Inertia::render('Guest/Emergency', [
            'contacts' => $contacts->map(fn (EmergencyContact $contact) => [
                'key' => $contact->key,
                'label' => $contact->label,
                'number' => $contact->number,
                'tel' => $contact->telHref(),
                'icon' => $contact->icon,
                'description' => $contact->description,
            ]),
            'fallbackContacts' => $contacts->take(1)->map(fn (EmergencyContact $contact) => [
                'key' => $contact->key,
                'label' => $contact->label.' (default)',
                'number' => $contact->number,
                'tel' => $contact->telHref(),
                'icon' => $contact->icon,
            ]),
            'groups' => $groups,
            'banner' => $banner ? [
                'title' => $banner->title,
                'message' => $banner->message,
                'startsAt' => $banner->starts_at?->toIso8601String(),
                'endsAt' => $banner->ends_at?->toIso8601String(),
            ] : null,
            'dataState' => $state,
            'cachedAt' => ($lastSync ?? now())->toIso8601String(),
        ]);
    }
}
