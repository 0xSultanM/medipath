<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Optional provider-backed medicine safety summary (PLAN Sprint 7.2).
 * Deterministic checks remain authoritative. AI text is always explicitly
 * marked with its source and never replaces the safety snapshot.
 */
class MedicationSafetyAiService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $safety
     * @return array{summary:string,status:string,source:string}
     */
    public function summarize(Patient $patient, array $items, array $safety): array
    {
        $fallback = $this->deterministic($safety);
        $enabled = (bool) $this->settings->get('ai_enabled', false);
        $key = (string) config('services.openai.key', '');
        $model = (string) $this->settings->get('ai_model', config('services.openai.model', 'fallback'));

        if (! $enabled || $key === '' || $model === 'fallback') {
            return ['summary' => $fallback, 'status' => 'fallback', 'source' => 'deterministic_fallback'];
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(max(3, (int) config('services.openai.timeout', 8)))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You summarize medication safety for a prescriber. Never diagnose, never give dosing advice, never claim certainty. The deterministic checks below are authoritative. Return strict JSON with key summary: a concise 2-3 sentence review of the deterministic findings and what the pharmacist must re-verify.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'patient_context' => [
                                    'age' => $patient->age,
                                    'gender' => $patient->gender,
                                    'condition' => $patient->condition,
                                ],
                                'medicines' => $items,
                                'deterministic_safety' => [
                                    'allergies' => $safety['allergies'] ?? [],
                                    'interactions' => $safety['interactions'] ?? [],
                                    'stock' => $safety['stock'] ?? [],
                                    'blocked' => $safety['blocked'] ?? false,
                                    'requires_override' => $safety['requiresOverride'] ?? false,
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return ['summary' => $fallback, 'status' => 'fallback', 'source' => 'deterministic_fallback'];
            }

            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);
            $summary = is_array($payload) ? trim((string) ($payload['summary'] ?? '')) : '';

            if ($summary === '') {
                return ['summary' => $fallback, 'status' => 'fallback', 'source' => 'deterministic_fallback'];
            }

            return ['summary' => Str::limit($summary, 1500), 'status' => 'ai', 'source' => 'ai'];
        } catch (\Throwable) {
            return ['summary' => $fallback, 'status' => 'fallback', 'source' => 'deterministic_fallback'];
        }
    }

    /** @param array<string, mixed> $safety */
    private function deterministic(array $safety): string
    {
        if (($safety['allergies'] ?? []) !== []) {
            return 'Allergy conflict detected. The prescription requires a sealed clinical override; pharmacy must independently re-verify the allergy and dispensing instructions.';
        }
        if (($safety['interactions'] ?? []) !== []) {
            return 'Pairwise interaction findings were recorded. Review management notes during dispensing; no interaction finding replaces pharmacist verification.';
        }
        if (($safety['stock'] ?? []) !== []) {
            return 'Stock or expiry warnings were found. Resolve availability before dispensing; the deterministic snapshot is authoritative.';
        }

        return 'Deterministic allergy, interaction, expiry and stock checks passed. Pharmacy will re-verify at dispensing.';
    }
}