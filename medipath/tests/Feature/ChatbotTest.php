<?php

use App\Models\ActivityLog;
use App\Models\SymptomCheckLog;

beforeEach(function () {
    $this->seed();
});

it('answers FAQ questions from the published knowledge base', function () {
    $this->postJson('/api/chatbot', ['message' => 'How do I cancel or reschedule an appointment?'])
        ->assertOk()
        ->assertJsonPath('kind', 'answer')
        ->assertJsonPath('source', 'faq')
        ->assertJsonStructure(['message', 'caption', 'suggestions'])
        ->assertJsonPath('caption', 'General guidance only — not a diagnosis.');
});

it('intercepts red flags before FAQ retrieval and halts triage', function () {
    $response = $this->postJson('/api/chatbot', [
        'message' => 'I have crushing chest pain with sweating — can I book an appointment?',
    ])->assertOk();

    $response->assertJsonPath('kind', 'emergency')
        ->assertJsonPath('halt', true)
        ->assertJsonPath('source', 'deterministic')
        ->assertJsonPath('category', 'Cardiac');

    expect($response->json('contacts'))->not->toBeEmpty()
        ->and($response->json('message'))->not->toContain('book an appointment');

    expect(SymptomCheckLog::query()->where('emergency_triggered', true)->count())->toBeGreaterThanOrEqual(2);
    expect(ActivityLog::query()->where('action', 'safety.emergency_rule_matched')->exists())->toBeTrue();
});

it('returns a deterministic fallback with suggestions when no FAQ matches', function () {
    $this->postJson('/api/chatbot', ['message' => 'Do you have parking at the clinic?'])
        ->assertOk()
        ->assertJsonPath('kind', 'answer')
        ->assertJsonPath('source', 'fallback')
        ->assertJsonPath('caption', 'General guidance only — not a diagnosis.')
        ->assertJsonStructure(['suggestions']);
});

it('validates message length', function () {
    $this->postJson('/api/chatbot', ['message' => 'a'])->assertStatus(422);
    $this->postJson('/api/chatbot', ['message' => str_repeat('a', 501)])->assertStatus(422);
});

it('rate limits the public chatbot per IP', function () {
    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->postJson('/api/chatbot', ['message' => 'How do bookings work?'])->assertOk();
    }

    $this->postJson('/api/chatbot', ['message' => 'How do bookings work?'])
        ->assertStatus(429);
});
