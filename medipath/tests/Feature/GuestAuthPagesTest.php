<?php

use App\Models\User;
use App\Services\PasswordResetService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('renders the login page with local demo accounts', function () {
    $this->app['env'] = 'local';

    $this->get('/login')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/Login')
            ->has('demoAccounts', 5)
            ->where('demoPassword', 'MediPath#2026')
    );
});

it('renders the multi-role registration page with database-driven options', function () {
    $this->app['env'] = 'local';

    $this->get('/register')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/Register')
            ->has('specialties')
            ->has('authorities')
            ->where('preselectedRole', 'patient')
            ->where('demoWalkInCode', 'WI-9001')
    );

    $this->get('/register?role=doctor')->assertInertia(
        fn (Assert $page) => $page->where('preselectedRole', 'doctor')
    );
});

it('renders the forgot and reset pages with a valid token state', function () {
    $user = User::query()->where('email', 'patient@medipath.test')->firstOrFail();
    $result = app(PasswordResetService::class)->request($user, '127.0.0.1');

    $this->get('/forgot-password')->assertInertia(
        fn (Assert $page) => $page->component('Guest/ForgotReset')->where('tokenValid', false)
    );

    $this->get('/reset-password/'.$result['token'].'?email='.urlencode($user->email))->assertInertia(
        fn (Assert $page) => $page->where('tokenValid', true)
    );
});

it('redirects an authenticated approved user away from login and register', function () {
    $admin = User::query()->where('email', 'admin@medipath.test')->firstOrFail();

    $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($admin)->get('/register')->assertRedirect(route('admin.dashboard'));
});

it('redirects an authenticated pending applicant to the holding screen', function () {
    $pending = User::query()->where('email', 'tanvir.alam@medipath.test')->firstOrFail();

    $this->actingAs($pending)->get('/login')->assertRedirect(route('pending.approval'));
});

it('honours a safe post-login redirect', function () {
    $this->post('/login', [
        'email' => 'patient@medipath.test',
        'password' => 'MediPath#2026',
        'redirect' => '/doctors',
    ])->assertRedirect('/doctors');
});

it('ignores an open redirect target', function () {
    $this->post('/login', [
        'email' => 'patient@medipath.test',
        'password' => 'MediPath#2026',
        'redirect' => '//evil.example',
    ])->assertRedirect(route('patient.dashboard'));
});
