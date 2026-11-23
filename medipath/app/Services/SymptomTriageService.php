<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\SymptomCheckLog;
use App\Models\SymptomSpecialtyMap;
use App\Support\ActivityActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Two-stage patient symptom triage (PLAN Sprints 5.1–5.3).
 *
 * Stage 1 is always the deterministic EmergencyRuleEngine — no network, no AI.
 * Stage 2 calls the configured AI provider only when enabled and keyed; any
 * failure falls back to a transparent deterministic guidance + specialty map
 * answer. The UI labels the source so fallback is never disguised as AI.
 */
class SymptomTriageService
{
    public function __construct(
        private readonly EmergencyRuleEngine $rules,
        private readonly SettingsService $settings,
        private readonly DoctorAvailabilityService $availability,
        private readonly DoctorPresenter $presenter,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * @param array{symptoms:string,duration:string,severity:int,onset:string,body_region:string} $data
     * @return array<string, mixed>
     */
    public function check(Patient $patient, array $data, Request $request): array
    {
        $symptoms = trim($data['symptoms']);
        $rule = $this->rules->match($symptoms);

        if ($rule) {
            $this->storeLog($patient, $data, [
                'halted' => true,
                'reason' => 'deterministic_red_flag',
                'rule_id' => $rule->id,
            ], [$rule->category], true, $rule->id, 'deterministic', $request);

            $this->activity->record(ActivityActions::EMERGENCY_RULE_MATCHED, $patient, [
                'surface' => 'patient_symptom_checker',
                'rule_id' => $rule->id,
                'category' => $rule->category,
                'message_excerpt' => Str::limit($symptoms, 160),
            ], $request->user());

            return [
                'kind' => 'emergency',
                'rule' => [
                    'id' => $rule->id,
                    'title' => $rule->title,
                    'category' => $rule->category,
                    'severity' => $rule->severity,
                    'directive' => $rule->action_directive,
                    'while_waiting' => [
                        'Stop activity; sit or semi-recline. Do not drive yourself.',
                        'Unlock the door and keep your phone nearby.',
                        'Call the emergency number now; do not wait for an online reply.',
                    ],
                ],
                'contacts' => $this->rules->contactsFor($rule)->map(fn ($contact) => [
                    'key' => $contact->key,
                    'label' => $contact->label,
                    'number' => $contact->number,
                    'tel' => $contact->telHref(),
                    'icon' => $contact->icon,
                ])->all(),
                'source' => 'deterministic',
                'ai_attempted' => false,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $recommendation = $this->specialtyFor($symptoms);
        $ai = $this->aiGuidance($data, $recommendation);

        if ($ai['specialty'] instanceof Specialty) {
            $recommendation = [
                'specialty' => $ai['specialty'],
                'matched_category' => $recommendation['matched_category'],
                'source' => 'ai_cross_validated',
            ];
        }

        $urgency = $ai['urgency'] ?? $this->urgency($data);
        $summary = $ai['summary'] ?? $this->fallbackSummary($data, $recommendation['specialty']);
        $questions = $ai['questions'] ?? $this->questions($recommendation['specialty'], $data);
        $source = $ai['source'];

        $response = [
            'kind' => 'guidance',
            'source' => $source,
            'urgency' => $urgency,
            'summary' => $summary,
            'questions' => array_values(array_slice($questions, 0, 5)),
            'specialty' => [
                'id' => $recommendation['specialty']?->id,
                'name' => $recommendation['specialty']?->name,
                'slug' => $recommendation['specialty']?->slug,
                'matched_category' => $recommendation['matched_category'],
                'browseUrl' => $recommendation['specialty'] ? route('patient.doctors', ['specialty' => $recommendation['specialty']->slug]) : route('patient.doctors'),
            ],
            'doctors' => $recommendation['specialty'] ? $this->doctorsFor($recommendation['specialty']) : [],
            'disclaimer' => 'General guidance only — not a diagnosis and not dosing advice. A clinician must assess you in person.',
            'checked_at' => now()->toIso8601String(),
        ];

        $this->storeLog($patient, $data, [
            'urgency' => $urgency,
            'summary' => $summary,
            'specialty' => $recommendation['specialty']?->name,
            'source' => $source,
        ], [], false, null, $source, $request);

        return $response;
    }

    /** @param array{symptoms:string,duration:string,severity:int,onset:string,body_region:string} $data
     *  @return array{specialty:?Specialty,matched_category:?string,source:string}
     */
    private function specialtyFor(string $symptoms): array
    {
        $normalized = Str::lower($symptoms);
        $best = null;
        $bestScore = 0;

        foreach (SymptomSpecialtyMap::query()->with('specialty')->where('is_active', true)->get() as $map) {
            $score = 0;
            foreach (($map->keywords ?? []) as $keyword) {
                if (str_contains($normalized, Str::lower((string) $keyword))) {
                    $score += 3;
                }
            }
            if (str_contains($normalized, Str::lower($map->symptom_category))) {
                $score += 1;
            }
            if ($score > $bestScore || ($score === $bestScore && $score > 0 && (int) $map->priority > (int) ($best?->priority ?? 0))) {
                $bestScore = $score;
                $best = $map;
            }
        }

        if ($best?->specialty) {
            return ['specialty' => $best->specialty, 'matched_category' => $best->symptom_category, 'source' => 'symptom_specialty_map'];
        }

        return [
            'specialty' => Specialty::query()->where('slug', 'general-medicine')->first()
                ?? Specialty::query()->where('is_active', true)->orderBy('name')->first(),
            'matched_category' => null,
            'source' => 'default',
        ];
    }

    /**
     * @param array{symptoms:string,duration:string,severity:int,onset:string,body_region:string} $data
     * @param array{specialty:?Specialty,matched_category:?string,source:string} $recommendation
     * @return array{summary:?string,urgency:?string,questions:?array,specialty:?Specialty,source:string}
     */
    private function aiGuidance(array $data, array $recommendation): array
    {
        $enabled = (bool) $this->settings->get('ai_enabled', false);
        $key = (string) config('services.openai.key', '');
        $model = (string) $this->settings->get('ai_model', config('services.openai.model', 'fallback'));

        if (! $enabled || $key === '' || $model === 'fallback') {
            return ['summary' => null, 'urgency' => null, 'questions' => null, 'specialty' => null, 'source' => 'deterministic_fallback'];
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(max(3, (int) config('services.openai.timeout', 8)))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a triage support assistant for a clinic. Never diagnose, never give dosing advice, never claim certainty. Red-flag rules have already been checked and cleared. Return strict JSON with keys: summary (string, 2-3 sentences), urgency (self_care|monitor|urgent), questions (array of 3-5 short patient questions), specialty_hint (one specialty name from: '.Specialty::query()->pluck('name')->implode(', ').').',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'symptoms' => $data['symptoms'],
                                'duration' => $data['duration'],
                                'severity_1_to_10' => $data['severity'],
                                'onset' => $data['onset'],
                                'body_region' => $data['body_region'],
                                'mapped_specialty' => $recommendation['specialty']?->name,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return ['summary' => null, 'urgency' => null, 'questions' => null, 'specialty' => null, 'source' => 'deterministic_fallback'];
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $payload = json_decode($content, true);

            if (! is_array($payload) || ! isset($payload['summary'])) {
                return ['summary' => null, 'urgency' => null, 'questions' => null, 'specialty' => null, 'source' => 'deterministic_fallback'];
            }

            $urgency = in_array(($payload['urgency'] ?? ''), ['self_care', 'monitor', 'urgent'], true)
                ? $payload['urgency'] : null;

            $questions = collect($payload['questions'] ?? [])
                ->filter(fn ($q) => is_string($q) && trim($q) !== '')
                ->map(fn ($q) => Str::limit(trim($q), 180))
                ->values()->all();

            $specialty = null;
            if (is_string($payload['specialty_hint'] ?? null)) {
                $specialty = Specialty::query()->where('is_active', true)
                    ->where(fn ($q) => $q->where('name', $payload['specialty_hint'])->orWhere('slug', Str::slug($payload['specialty_hint'])))
                    ->first();
            }

            return [
                'summary' => Str::limit(trim((string) $payload['summary']), 1200),
                'urgency' => $urgency,
                'questions' => $questions ?: null,
                'specialty' => $specialty,
                'source' => 'ai',
            ];
        } catch (\Throwable) {
            return ['summary' => null, 'urgency' => null, 'questions' => null, 'specialty' => null, 'source' => 'deterministic_fallback'];
        }
    }

    /** @param array{symptoms:string,duration:string,severity:int,onset:string,body_region:string} $data */
    private function urgency(array $data): string
    {
        if ((int) $data['severity'] >= 8 || ($data['onset'] === 'sudden' && (int) $data['severity'] >= 6)) {
            return 'urgent';
        }
        if ((int) $data['severity'] >= 5 || in_array($data['duration'], ['1-2 weeks', '> 2 weeks'], true)) {
            return 'monitor';
        }

        return 'self_care';
    }

    /** @param array{symptoms:string,duration:string,severity:int,onset:string,body_region:string} $data */
    private function fallbackSummary(array $data, ?Specialty $specialty): string
    {
        $name = $specialty?->name ?? 'a clinician';

        return 'You reported '.Str::limit($data['symptoms'], 180, '…').' for '.$data['duration'].' with severity '.(int) $data['severity'].'/10. '
            .'No emergency red-flag phrase matched. '.($this->urgency($data) === 'urgent'
                ? 'Please arrange prompt in-person assessment today; if symptoms worsen, call emergency services.'
                : 'Arrange a '.$name.' consultation if symptoms persist, worsen, or interfere with daily activity. '
                    .'This is general guidance, not a diagnosis.');
    }

    /** @return array<int, string> */
    private function questions(?Specialty $specialty, array $data): array
    {
        return [
            'Which warning signs should make me seek emergency care before my visit?',
            'Could any medicine I already take interact with a likely treatment?',
            'Should I keep a symptom diary (timing, triggers, severity) before the consultation?',
            'Is a '.($specialty?->name ?? 'clinician').' appointment the right setting for this, or should I seek urgent care?',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function doctorsFor(Specialty $specialty): array
    {
        return Doctor::query()
            ->verified()
            ->where('specialty_id', $specialty->id)
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->with(['user', 'specialty'])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->limit(6)
            ->get()
            ->map(fn (Doctor $doctor) => $this->presenter->summary($doctor, $this->availability->nextAvailable($doctor, 14)))
            ->sortBy(fn (array $doctor) => $doctor['nextAvailable'] ?? '')
            ->take(3)
            ->map(fn (array $doctor) => [
                ...$doctor,
                'bookingUrl' => route('patient.book', ['doctorCode' => Doctor::query()->whereKey($doctor['id'])->value('doctor_code')]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $aiResponse
     * @param array<int, string> $matchedRules
     */
    private function storeLog(Patient $patient, array $data, array $aiResponse, array $matchedRules, bool $emergency, ?int $ruleId, string $source, Request $request): void
    {
        SymptomCheckLog::query()->create([
            'patient_id' => $patient->id,
            'session_key' => hash('sha256', $request->ip().'|'.(string) $request->userAgent()),
            'symptoms' => $data,
            'ai_response' => $aiResponse,
            'matched_rules' => $matchedRules,
            'emergency_triggered' => $emergency,
            'matched_rule_id' => $ruleId,
            'source' => $source,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}