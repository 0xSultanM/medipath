<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\SymptomCheckLog;
use App\Support\ActivityActions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * FAQ chatbot pipeline (PLAN Sprint 1.7):
 *   1. deterministic red-flag intercept  → emergency response, halt;
 *   2. published FAQ keyword retrieval   → grounded answer;
 *   3. deterministic fallback copy       → suggestions, never a blank screen.
 *
 * AI retrieval is deliberately not called in Phase 1; Phase 5/7 add AiClient
 * behind the same contract, always AFTER this rule engine.
 */
class ChatbotService
{
    private const STOP_WORDS = [
        'the', 'and', 'for', 'you', 'your', 'are', 'can', 'how', 'what', 'where', 'when',
        'why', 'who', 'does', 'did', 'with', 'from', 'that', 'this', 'have', 'has', 'was',
        'were', 'will', 'would', 'could', 'should', 'about', 'into', 'not', 'but', 'any',
        'get', 'got', 'please', 'help', 'need', 'want', 'there', 'here', 'whats',
    ];

    public function __construct(
        private readonly EmergencyRuleEngine $rules,
        private readonly ActivityLogService $activity,
    ) {}

    /** @return array<string, mixed> */
    public function reply(string $message, Request $request): array
    {
        $message = trim($message);
        $rule = $this->rules->match($message);

        if ($rule) {
            $this->logEmergency($message, $rule->id, $rule->category, $request);

            return [
                'kind' => 'emergency',
                'title' => $rule->title,
                'message' => $rule->action_directive,
                'category' => $rule->category,
                'rule_id' => $rule->id,
                'halt' => true,
                'source' => 'deterministic',
                'contacts' => $this->rules->contactsFor($rule)->map(fn ($contact) => [
                    'key' => $contact->key,
                    'label' => $contact->label,
                    'number' => $contact->number,
                    'tel' => $contact->telHref(),
                    'icon' => $contact->icon,
                ])->all(),
            ];
        }

        $faq = $this->bestFaq($message);

        return [
            'kind' => 'answer',
            'message' => $faq?->answer
                ?? 'I can help with bookings, prescriptions, bills, account approval and emergency information. Try one of the suggested questions, or contact the clinic desk for anything clinical.',
            'source' => $faq ? 'faq' : 'fallback',
            'faq_id' => $faq?->id,
            'caption' => 'General guidance only — not a diagnosis.',
            'suggestions' => $this->suggestions($faq?->id),
        ];
    }

    private function bestFaq(string $message): ?Faq
    {
        $tokens = $this->tokens($message);

        if ($tokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach (Faq::query()->published()->get() as $faq) {
            $question = Str::lower($faq->question);
            $answer = Str::lower($faq->answer);
            $keywords = Str::lower((string) $faq->keywords);
            $score = 0;

            foreach ($tokens as $token) {
                if (str_contains($keywords, $token)) {
                    $score += 3;
                }
                if (str_contains($question, $token)) {
                    $score += 2;
                }
                if (str_contains($answer, $token)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        return $bestScore >= 2 ? $best : null;
    }

    /** @return array<int, string> */
    private function tokens(string $message): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', Str::lower($message)) ?? '';
        $words = preg_split('/\s+/', trim($normalized)) ?: [];

        return collect($words)
            ->filter(fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function suggestions(?int $excludeId = null): array
    {
        return Faq::query()->published()
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->orderBy('sort_order')
            ->limit(3)
            ->pluck('question')
            ->all();
    }

    private function logEmergency(string $message, int $ruleId, string $category, Request $request): void
    {
        $sessionKey = hash('sha256', $request->ip().'|'.(string) $request->userAgent());

        SymptomCheckLog::query()->create([
            'patient_id' => null,
            'session_key' => $sessionKey,
            'symptoms' => [
                'message' => Str::limit($message, 1000),
                'surface' => 'guest_chatbot',
            ],
            'ai_response' => ['halted' => true, 'reason' => 'deterministic_red_flag'],
            'matched_rules' => [$category],
            'emergency_triggered' => true,
            'matched_rule_id' => $ruleId,
            'source' => 'deterministic_fallback',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $this->activity->record(
            ActivityActions::EMERGENCY_RULE_MATCHED,
            detail: [
                'surface' => 'guest_chatbot',
                'rule_id' => $ruleId,
                'category' => $category,
                'message_excerpt' => Str::limit($message, 160),
            ],
        );
    }
}
