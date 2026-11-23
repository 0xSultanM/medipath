<?php

use App\Mail\PasswordResetLink;
use App\Models\PasswordReset;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('sends a generic success response and stores only a hashed token', function () {
    Mail::fake();
    $user = makeUser(['email' => 'reset.user@medipath.test']);

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('success');

    $token = null;
    Mail::assertQueued(PasswordResetLink::class, function (PasswordResetLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    $row = PasswordReset::query()->where('email', $user->email)->firstOrFail();

    expect($token)->not->toBeNull()
        ->and($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token)
        ->and($row->expires_at->greaterThan(now()->addMinutes(29)));
});

it('does not reveal whether an email exists', function () {
    Mail::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'unknown@medipath.test'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('success');

    Mail::assertNothingQueued();
    expect(PasswordReset::query()->count())->toBe(0);
});

it('validates the reset page and resets the password once', function () {
    $user = makeUser(['email' => 'once.user@medipath.test']);
    $result = app(PasswordResetService::class)->request($user, '127.0.0.1');

    $this->get('/reset-password/'.$result['token'].'?email='.urlencode($user->email))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Guest/ForgotReset')->where('tokenValid', true));

    $this->post('/reset-password', [
        'email' => $user->email,
        'token' => $result['token'],
        'password' => 'Brand#New2026',
        'password_confirmation' => 'Brand#New2026',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Brand#New2026', $user->fresh()->password))->toBeTrue();
    expect(PasswordReset::query()->where('email', $user->email)->value('consumed_at'))->not->toBeNull();

    // Single use
    $this->post('/reset-password', [
        'email' => $user->email,
        'token' => $result['token'],
        'password' => 'Again#New2026',
        'password_confirmation' => 'Again#New2026',
    ])->assertSessionHasErrors('token');
});

it('rejects an expired token', function () {
    $user = makeUser(['email' => 'expired.user@medipath.test']);
    $result = app(PasswordResetService::class)->request($user, '127.0.0.1');

    PasswordReset::query()->where('email', $user->email)->update(['expires_at' => now()->subMinute()]);

    $this->post('/reset-password', [
        'email' => $user->email,
        'token' => $result['token'],
        'password' => 'Brand#New2026',
        'password_confirmation' => 'Brand#New2026',
    ])->assertSessionHasErrors('token');

    expect(Hash::check('MediPath#2026', $user->fresh()->password))->toBeTrue();
});

it('revokes sessions created before the password change', function () {
    $user = makeUser(['email' => 'session.user@medipath.test']);
    makePatientRecord($user);

    $user->forceFill(['password_changed_at' => now()->addMinute()])->save();

    $this->actingAs($user)->get('/patient/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('allows a fresh login after the password change marker is set', function () {
    $user = makeUser(['email' => 'fresh.session@medipath.test']);
    makePatientRecord($user);
    $user->forceFill(['password_changed_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'MediPath#2026'])
        ->assertRedirect(route('patient.dashboard'));

    $this->get('/patient/dashboard')->assertOk();
});
