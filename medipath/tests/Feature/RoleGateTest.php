<?php

it('redirects guests away from role shells', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('login'));
});

it('blocks a patient from the admin area with 403', function () {
    $patient = makeUser(['role' => 'patient']);
    makePatientRecord($patient);

    $this->actingAs($patient)->get('/admin/dashboard')->assertForbidden();
});

it('allows an admin into the admin shell', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
});

it('blocks an unverified doctor from clinical routes', function () {
    $user = makeUser(['role' => 'doctor', 'verification_status' => 'pending']);
    makeDoctorRecord($user, ['verification_status' => 'pending']);

    $this->actingAs($user)->get('/doctor/dashboard')->assertForbidden();
});

it('blocks a doctor whose user is verified but profile is not', function () {
    $user = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    makeDoctorRecord($user, ['verification_status' => 'pending']);

    $this->actingAs($user)->get('/doctor/dashboard')->assertForbidden();
});

it('allows a verified doctor into the clinical shell', function () {
    $user = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    makeDoctorRecord($user, ['verification_status' => 'verified']);

    $this->actingAs($user)->get('/doctor/dashboard')->assertOk();
});

it('blocks an approved pharmacist without a profile row', function () {
    $user = makeUser(['role' => 'pharmacist']);

    $this->actingAs($user)->get('/pharmacist/dashboard')->assertForbidden();
});

it('allows an approved pharmacist with a profile into the pharmacy shell', function () {
    $user = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($user);

    $this->actingAs($user)->get('/pharmacist/dashboard')->assertOk();
});

it('blocks a suspended account even with the right role', function () {
    $admin = makeUser(['role' => 'admin', 'status' => 'suspended', 'suspended_at' => now()]);

    $this->actingAs($admin)->get('/admin/dashboard')->assertRedirect(route('login'));
});

it('returns a JSON 403 for role abuse from an API client', function () {
    $patient = makeUser(['role' => 'patient']);
    makePatientRecord($patient);

    $this->actingAs($patient)->getJson('/admin/dashboard')
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have access to this area.');
});
