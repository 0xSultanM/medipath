<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use App\Models\Specialty;
use App\Models\SymptomSpecialtyMap;
use App\Services\ActivityLogService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Emergency rules, hotlines and symptom→specialty mapping (admin/11, Sprint 3.8). */
class SafetyController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'mapping' ? 'mapping' : 'rules';

        $contacts = EmergencyContact::query()->orderBy('sort_order')->get();
        $rules = EmergencyRule::query()->with('hotline:id,key,label,number')->orderBy('sort_order')->get();
        $mappings = SymptomSpecialtyMap::query()->with('specialty:id,name')->orderBy('symptom_category')->orderBy('priority')->get();

        $forced = (string) $request->query('state', '');
        $activeCount = $tab === 'mapping' ? $mappings->count() : ($contacts->count() + $rules->count());
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($activeCount === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Safety', [
            'tab' => $tab,
            'contacts' => $contacts->map(fn (EmergencyContact $contact): array => [
                'id' => $contact->id,
                'key' => $contact->key,
                'label' => $contact->label,
                'number' => $contact->number,
                'icon' => $contact->icon,
                'description' => $contact->description,
                'sortOrder' => $contact->sort_order,
                'isActive' => $contact->is_active,
            ])->values(),
            'rules' => $rules->map(fn (EmergencyRule $rule): array => [
                'id' => $rule->id,
                'category' => $rule->category,
                'title' => $rule->title,
                'keywords' => $rule->symptom_keywords,
                'keywordsText' => implode(', ', $rule->symptom_keywords ?? []),
                'severity' => $rule->severity,
                'actionDirective' => $rule->action_directive,
                'hotlineKey' => $rule->hotline_key,
                'hotlineLabel' => $rule->hotline?->label,
                'sortOrder' => $rule->sort_order,
                'isActive' => $rule->is_active,
            ])->values(),
            'mappings' => $mappings->map(fn (SymptomSpecialtyMap $map): array => [
                'id' => $map->id,
                'symptomCategory' => $map->symptom_category,
                'keywords' => $map->keywords,
                'keywordsText' => implode(', ', $map->keywords ?? []),
                'specialtyId' => $map->specialty_id,
                'specialty' => $map->specialty?->name,
                'priority' => $map->priority,
                'isActive' => $map->is_active,
            ])->values(),
            'specialties' => Specialty::query()->active()->orderBy('name')->get(['id', 'name']),
            'hotlines' => $contacts->where('is_active', true)->map(fn (EmergencyContact $c): array => [
                'key' => $c->key,
                'label' => $c->label,
                'number' => $c->number,
            ])->values(),
            'counts' => [
                'contacts' => $contacts->count(),
                'rules' => $rules->count(),
                'mappings' => $mappings->count(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $validated = $this->validateContact($request);
        $contact = EmergencyContact::query()->create($validated);
        $this->log($request, 'contact_created', $contact, ['key' => $contact->key]);

        return back()->with('success', 'Emergency contact added.');
    }

    public function updateContact(Request $request, EmergencyContact $contact): RedirectResponse
    {
        $contact->forceFill($this->validateContact($request, $contact))->save();
        $this->log($request, 'contact_updated', $contact, ['key' => $contact->key]);

        return back()->with('success', 'Emergency contact updated.');
    }

    public function toggleContact(Request $request, EmergencyContact $contact): RedirectResponse
    {
        $contact->forceFill(['is_active' => ! $contact->is_active])->save();
        $this->log($request, 'contact_toggled', $contact, ['is_active' => $contact->is_active]);

        return back()->with('success', $contact->is_active ? 'Contact active.' : 'Contact hidden from the emergency page.');
    }

    public function updateRule(Request $request, EmergencyRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:180'],
            'keywords' => ['required', 'string', 'max:1000'],
            'severity' => ['required', Rule::in(['critical', 'warning', 'info'])],
            'action_directive' => ['required', 'string', 'max:2000'],
            'hotline_key' => ['nullable', 'string', Rule::exists('emergency_contacts', 'key')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $rule->forceFill([
            'category' => trim($validated['category']),
            'title' => trim($validated['title']),
            'symptom_keywords' => $this->keywords($validated['keywords']),
            'severity' => $validated['severity'],
            'action_directive' => trim($validated['action_directive']),
            'hotline_key' => $validated['hotline_key'] ?: null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ])->save();

        $this->log($request, 'rule_updated', $rule, ['category' => $rule->category]);

        return back()->with('success', 'Red-flag rule updated. The deterministic engine uses the new keywords on the next request.');
    }

    public function toggleRule(Request $request, EmergencyRule $rule): RedirectResponse
    {
        $rule->forceFill(['is_active' => ! $rule->is_active])->save();
        $this->log($request, 'rule_toggled', $rule, ['is_active' => $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Rule active.' : 'Rule disabled — cached/deterministic paths no longer match it.');
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $validated = $this->validateMapping($request);
        $mapping = SymptomSpecialtyMap::query()->create($validated);
        $this->log($request, 'mapping_created', $mapping, ['category' => $mapping->symptom_category]);

        return back()->with('success', 'Symptom mapping added.');
    }

    public function updateMapping(Request $request, SymptomSpecialtyMap $mapping): RedirectResponse
    {
        $mapping->forceFill($this->validateMapping($request, $mapping))->save();
        $this->log($request, 'mapping_updated', $mapping, ['category' => $mapping->symptom_category]);

        return back()->with('success', 'Symptom mapping updated.');
    }

    public function toggleMapping(Request $request, SymptomSpecialtyMap $mapping): RedirectResponse
    {
        $mapping->forceFill(['is_active' => ! $mapping->is_active])->save();
        $this->log($request, 'mapping_toggled', $mapping, ['is_active' => $mapping->is_active]);

        return back()->with('success', $mapping->is_active ? 'Mapping active.' : 'Mapping disabled.');
    }

    /** @return array<string, mixed> */
    private function validateContact(Request $request, ?EmergencyContact $contact = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('emergency_contacts', 'key')->ignore($contact?->id)],
            'label' => ['required', 'string', 'max:120'],
            'number' => ['required', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:12'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'key' => $validated['key'],
            'label' => trim($validated['label']),
            'number' => trim($validated['number']),
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    private function validateMapping(Request $request, ?SymptomSpecialtyMap $mapping = null): array
    {
        $validated = $request->validate([
            'symptom_category' => [
                'required', 'string', 'max:120',
                Rule::unique('symptom_specialty_map', 'symptom_category')
                    ->where(fn ($query) => $query->where('specialty_id', (int) $request->input('specialty_id')))
                    ->ignore($mapping?->id),
            ],
            'keywords' => ['required', 'string', 'max:1000'],
            'specialty_id' => ['required', 'integer', Rule::exists('specialties', 'id')->where('is_active', true)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'symptom_category' => trim($validated['symptom_category']),
            'keywords' => $this->keywords($validated['keywords']),
            'specialty_id' => (int) $validated['specialty_id'],
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    /** @return array<int, string> */
    private function keywords(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $detail */
    private function log(Request $request, string $event, object $target, array $detail): void
    {
        $this->activity->record(ActivityActions::SAFETY_UPDATED, $target, [
            'event' => $event,
            ...$detail,
        ], $request->user());
    }
}