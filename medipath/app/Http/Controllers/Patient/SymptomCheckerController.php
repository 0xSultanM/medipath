<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Models\SymptomCheckLog;
use App\Services\SymptomTriageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Authenticated FAQ/symptom assistant surface (PLAN Sprint 4.11; AI is Phase 5). */
class SymptomCheckerController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $recent = SymptomCheckLog::query()
            ->where('patient_id', $patient->id)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (SymptomCheckLog $log): array => [
                'id' => $log->id,
                'summary' => Str::limit(is_array($log->symptoms)
                    ? (string) ($log->symptoms['symptoms'] ?? collect($log->symptoms)->filter(fn ($v) => is_string($v))->implode(' · '))
                    : (string) $log->symptoms, 120),
                'emergencyTriggered' => $log->emergency_triggered,
                'source' => $log->source,
                'sourceLabel' => $log->source === 'ai' ? 'AI-assisted' : ($log->emergency_triggered ? 'Emergency rule' : 'Deterministic fallback'),
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Patient/SymptomChecker', [
            'suggestions' => [
                'How do I book an appointment?',
                'Can I download my prescription?',
                'What is the cancellation policy?',
                'How do I update my allergies?',
            ],
            'contacts' => EmergencyContact::query()->active()->get()->map(fn (EmergencyContact $contact): array => [
                'key' => $contact->key,
                'label' => $contact->label,
                'number' => $contact->number,
                'tel' => $contact->telHref(),
                'icon' => $contact->icon,
            ])->values(),
            'offlineAnswer' => 'Appointments are booked from Find Doctors. Prescriptions and bills are in your portal. For red-flag symptoms, call the emergency hotline immediately.',
            'recentChecks' => $recent,
            'counts' => [
                'checks' => SymptomCheckLog::query()->where('patient_id', $patient->id)->count(),
            ],
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function check(Request $request, SymptomTriageService $triage): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $validated = $request->validate([
            'symptoms' => ['required', 'string', 'min:5', 'max:1000'],
            'duration' => ['required', Rule::in(['< 1 day', '2-5 days', '1-2 weeks', '> 2 weeks'])],
            'severity' => ['required', 'integer', 'min:1', 'max:10'],
            'onset' => ['required', Rule::in(['gradual', 'sudden', 'comes_and_goes'])],
            'body_region' => ['required', Rule::in(['head', 'throat_neck', 'chest', 'abdomen', 'limbs', 'whole_body', 'other'])],
            'consent' => ['accepted'],
        ]);

        $result = $triage->check($patient, $validated, $request);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}