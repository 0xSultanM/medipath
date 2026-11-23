<?php

use App\Models\Doctor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('renders a verified doctor with seven-day availability and approved reviews only', function () {
    $doctor = Doctor::query()->where('doctor_code', 'DOC-1001')->firstOrFail();

    $this->get('/doctors/DOC-1001')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/DoctorProfile')
            ->where('doctor.name', 'Dr. Ayesha Rahman')
            ->has('availability', 7)
            ->where('reviews.meta.total', 1)
            ->has('reviews.data', 1)
            ->where('ratingSummary.distribution.5', 1)
            ->where('bookingGate.label', 'Sign in to book')
    );

    expect($doctor->doctor_code)->toBe('DOC-1001');
});

it('marks leave days with no open slots', function () {
    $response = $this->get('/doctors/DOC-1001')->assertOk();
    $availability = collect($response->viewData('page')['props']['availability']);

    expect($availability->where('status', 'leave')->count())->toBeGreaterThanOrEqual(2)
        ->and($availability->where('status', 'leave')->sum('open_slots'))->toBe(0);
});

it('hides pending or unverified doctor profiles from the public', function () {
    $this->get('/doctors/DOC-1005')->assertNotFound();
});

it('shows the signed-in patient a portal booking gate', function () {
    $patient = User::query()->where('email', 'patient@medipath.test')->firstOrFail();

    $this->actingAs($patient)->get('/doctors/DOC-1001')->assertInertia(
        fn (Assert $page) => $page->where('bookingGate.label', 'Continue in patient portal')
    );
});

it('supports the full forced state set', function () {
    $this->get('/doctors/DOC-1001?state=error')->assertInertia(
        fn (Assert $page) => $page->where('availabilityState', 'error')->where('reviewsState', 'error')
    );
});
