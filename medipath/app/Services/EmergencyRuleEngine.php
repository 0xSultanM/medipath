<?php

namespace App\Services;

use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic red-flag interception (PLAN Sprint 1.7 / 5.3).
 * Runs BEFORE any FAQ or AI retrieval. The first rule that contains a keyword
 * wins by configured sort order. Never guesses.
 */
class EmergencyRuleEngine
{
    public function match(string $message): ?EmergencyRule
    {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return null;
        }

        foreach (EmergencyRule::query()->active()->get() as $rule) {
            foreach (($rule->symptom_keywords ?? []) as $keyword) {
                $needle = $this->normalize((string) $keyword);
                if ($needle !== '' && str_contains($normalized, $needle)) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Hotline(s) for a matched rule: its configured hotline first, then the
     * national emergency contact, then any active contact (safe fallback).
     *
     * @return Collection<int, EmergencyContact>
     */
    public function contactsFor(EmergencyRule $rule): Collection
    {
        $contacts = EmergencyContact::query()->active()->get();

        $primary = $rule->hotline_key
            ? $contacts->firstWhere('key', $rule->hotline_key)
            : null;

        $national = $contacts->firstWhere('key', 'national_emergency');
        $support = $contacts->whereIn('key', ['ambulance', 'clinic_desk']);

        return collect([$primary, $national])
            ->merge($support)
            ->filter()
            ->unique('key')
            ->values()
            ->take(3)
            ->whenEmpty(fn () => $contacts->take(1)->values());
    }

    /** Lowercase, strip punctuation, collapse whitespace. */
    private function normalize(string $value): string
    {
        $value = Str::lower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
