<?php

use App\Models\ActivityLog;
use App\Models\PendingApproval;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login|admin@medipath.test|127.0.0.1');
});

it('signs an admin in and writes an append-only activity entry', function () {
    $admin = makeUser(['role' => 'admin', 'email' => 'admin@medipath.test']);

    $this->post('/login', ['email' => $admin->email, 'password' => 'MediPath#2026'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
    expect(ActivityLog::query()->where('action', ActivityActions::AUTH_LOGIN)->count())->toBe(1);
    expect($admin->fresh()->last_login_at)->not->toBeNull();
});

it('throttles brute-force sign-in attempts after five failures', function () {
    makeUser(['email' => 'admin@medipath.test', 'role' => 'admin']);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/login', [
            'email' => 'admin@medipath.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/login', [
        'email' => 'admin@medipath.test',
        'password' => 'wrong-password',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'too_many_attempts');

    expect(ActivityLog::query()->where('action', ActivityActions::AUTH_LOGIN_FAILED)->count())->toBe(6);
});

it('lets a pending applicant sign in only to reach the holding experience', function () {
    $pending = makeUser([
        'email' => 'pending@medipath.test',
        'role' => 'doctor',
        'status' => 'pending',
        'verification_status' => 'pending',
    ]);

    $this->post('/login', [
        'email' => $pending->email,
        'password' => 'MediPath#2026',
    ])->assertRedirect(route('pending.approval'));

    $this->assertAuthenticatedAs($pending);

    // Every role portal stays blocked until approval.
    $this->get('/doctor/dashboard')->assertRedirect(route('pending.approval'));
    $this->get('/pending')->assertOk();
});

it('lets a rejected applicant sign in to read the rejection reason', function () {
    $rejected = makeUser([
        'email' => 'rejected@medipath.test',
        'role' => 'doctor',
        'status' => 'rejected',
        'verification_status' => 'pending',
    ]);

    PendingApproval::query()->create([
        'user_id' => $rejected->id,
        'requested_role' => 'doctor',
        'status' => 'rejected',
        'review_reason' => 'License image unreadable.',
        'reviewed_at' => now(),
    ]);

    $this->post('/login', [
        'email' => $rejected->email,
        'password' => 'MediPath#2026',
    ])->assertRedirect(route('pending.approval'));

    $this->assertAuthenticatedAs($rejected);
    $this->get('/pending')->assertOk();
});

it('refuses a suspended account at sign-in', function () {
    $suspended = makeUser([
        'email' => 'suspended@medipath.test',
        'role' => 'doctor',
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);

    $this->postJson('/login', [
        'email' => $suspended->email,
        'password' => 'MediPath#2026',
    ])->assertStatus(422);

    $this->assertGuest();
});

it('revokes the session and logs the sign-out event', function () {
    $admin = makeUser(['role' => 'admin', 'email' => 'admin@medipath.test']);
    $this->actingAs($admin);

    $this->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
    expect(ActivityLog::query()->where('action', ActivityActions::AUTH_LOGOUT)->count())->toBe(1);
});

it('does not leak whether an email exists when credentials fail', function () {
    makeUser(['email' => 'known@medipath.test']);

    $this->postJson('/login', ['email' => 'unknown@medipath.test', 'password' => 'whatever'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $messageUnknown = $this->postJson('/login', ['email' => 'unknown@medipath.test', 'password' => 'whatever'])
        ->json('errors.email.0');

    RateLimiter::clear('login|known@medipath.test|127.0.0.1');

    $messageKnown = $this->postJson('/login', ['email' => 'known@medipath.test', 'password' => 'whatever'])
        ->json('errors.email.0');

    expect($messageUnknown)->toBe($messageKnown);
});

it('persists an explicit theme preference for the authenticated user', function () {
    $user = makeUser();

    $this->actingAs($user)
        ->postJson('/preferences/theme', ['theme' => 'dark'])
        ->assertOk()
        ->assertJsonPath('theme', 'dark');

    expect($user->fresh()->preferences->theme)->toBe('dark');
});
