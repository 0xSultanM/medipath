<?php

use App\Models\ActivityLog;
use App\Models\EmergencyContact;
use App\Models\Specialty;
use App\Models\SymptomCheckLog;
use App\Models\SymptomSpecialtyMap;
use App\Services\SettingsService;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function triagePayload(array $overrides = []): array
{
    return [
        'symptoms' => 'Sore throat and fever for three days; swallowing is painful.',
        'duration' => '2-5 days',
        'severity' => 4,
        'onset' => 'gradual',
        'body_region' => 'throat_neck',
        'consent' => true,
        ...$overrides,
    ];
}

function makeTriageSpecialties(): void
{
    $general = makeSpecialty('General Medicine');
    $ent = makeSpecialty('Otolaryngology (ENT)');
    $cardiology = makeSpecialty('Cardiology');

    SymptomSpecialtyMap::query()->create([
        'symptom_category' => 'Throat & fever',
        'keywords' => ['sore throat', 'fever', 'swallowing'],
        'specialty_id' => $ent->id,
        'priority' => 10,
        'is_active' => true,
    ]);

    SymptomSpecialtyMap::query()->create([
        'symptom_category' => 'Chest pain & palpitations',
        'keywords' => ['chest pain', 'palpitations'],
        'specialty_id' => $cardiology->id,
        'priority' => 10,
        'is_active' => true,
    ]);
}

it('renders the two-stage symptom checker page', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $this->actingAs($patientUser)->get('/patient/symptom-checker')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Patient/SymptomChecker')
            ->has('suggestions')
            ->has('contacts'));
});

it('halts at the deterministic red-flag stage and writes an emergency audit', function () {
    $patientUser = makeUser(['role' => 'patient']);
    $patient = makePatientRecord($patientUser);
    EmergencyContact::query()->create([
        'key' => 'national_emergency',
        'label' => 'National emergency',
        'number' => '999',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    \App\Models\EmergencyRule::query()->create([
        'category' => 'Cardiac',
        'title' => 'Crushing chest pain',
        'symptom_keywords' => ['crushing chest pain'],
        'severity' => 'critical',
        'action_directive' => 'Call emergency services now. Do not drive yourself.',
        'hotline_key' => 'national_emergency',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($patientUser)->postJson('/patient/symptom-check', triagePayload([
        'symptoms' => 'I have crushing chest pain spreading to my left arm.',
    ]));

    $response->assertOk()
        ->assertJsonPath('kind', 'emergency')
        ->assertJsonPath('source', 'deterministic')
        ->assertJsonPath('rule.category', 'Cardiac');

    $log = SymptomCheckLog::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($log->emergency_triggered)->toBeTrue()
        ->and($log->source)->toBe('deterministic')
        ->and($log->matched_rule_id)->not->toBeNull();

    expect(ActivityLog::query()->where('action', ActivityActions::EMERGENCY_RULE_MATCHED)->exists())->toBeTrue();
});

it('returns deterministic fallback guidance with a mapped specialty when AI is off', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);
    makeTriageSpecialties();
    app(SettingsService::class)->set('ai_enabled', false);

    $response = $this->actingAs($patientUser)->postJson('/patient/symptom-check', triagePayload());

    $response->assertOk()
        ->assertJsonPath('kind', 'guidance')
        ->assertJsonPath('source', 'deterministic_fallback')
        ->assertJsonPath('specialty.name', 'Otolaryngology (ENT)')
        ->assertJsonPath('urgency', 'self_care');

    expect(SymptomCheckLog::query()->where('emergency_triggered', false)->exists())->toBeTrue();
});

it('uses AI Stage-2 when configured and cross-validates its specialty hint', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);
    makeTriageSpecialties();
    app(SettingsService::class)->set('ai_enabled', true);
    app(SettingsService::class)->set('ai_model', 'gpt-4o-mini');
    config(['services.openai.key' => 'test-key', 'services.openai.base_url' => 'https://api.openai.test/v1']);

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary' => 'Your symptoms are commonly viral and do not currently suggest an emergency.',
                    'urgency' => 'monitor',
                    'questions' => ['Should I get a throat swab?', 'What fever pattern should worry me?', 'Could my current medicines interact?'],
                    'specialty_hint' => 'Cardiology',
                ])],
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($patientUser)->postJson('/patient/symptom-check', triagePayload());

    $response->assertOk()
        ->assertJsonPath('kind', 'guidance')
        ->assertJsonPath('source', 'ai')
        ->assertJsonPath('urgency', 'monitor')
        ->assertJsonPath('specialty.name', 'Cardiology');

    expect($response->json('questions'))->toHaveCount(3);
});

it('falls back safely when the AI provider fails', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);
    makeTriageSpecialties();
    app(SettingsService::class)->set('ai_enabled', true);
    app(SettingsService::class)->set('ai_model', 'gpt-4o-mini');
    config(['services.openai.key' => 'test-key', 'services.openai.base_url' => 'https://api.openai.test/v1']);
    Http::fake(['api.openai.test/v1/chat/completions' => Http::response(['error' => 'down'], 503)]);

    $this->actingAs($patientUser)->postJson('/patient/symptom-check', triagePayload())
        ->assertOk()
        ->assertJsonPath('kind', 'guidance')
        ->assertJsonPath('source', 'deterministic_fallback')
        ->assertJsonPath('specialty.name', 'Otolaryngology (ENT)');
});

it('requires consent and blocks other roles', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $this->actingAs($patientUser)->postJson('/patient/symptom-check', triagePayload(['consent' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('consent');

    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    makeDoctorRecord($doctorUser);
    $this->actingAs($doctorUser)->postJson('/patient/symptom-check', triagePayload())->assertForbidden();
});