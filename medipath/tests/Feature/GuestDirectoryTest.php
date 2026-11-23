<?php

use App\Models\Specialty;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('lists only verified doctors with server-side pagination metadata', function () {
    $this->get('/doctors')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/Directory')
            ->has('doctors', 4)
            ->where('meta.total', 4)
            ->where('dataState', 'success')
    );
});

it('applies search, specialty, rating, fee sort and weekday filters through the URL', function () {
    $cardiology = Specialty::query()->where('slug', 'cardiology')->firstOrFail();

    $this->get('/doctors?specialty='.$cardiology->slug)->assertInertia(
        fn (Assert $page) => $page->has('doctors', 1)
            ->where('doctors.0.specialty', 'Cardiology')
    );

    $this->get('/doctors?q=Ayesha')->assertInertia(
        fn (Assert $page) => $page->has('doctors', 1)->where('doctors.0.name', 'Dr. Ayesha Rahman')
    );

    $this->get('/doctors?min_rating=4.9')->assertInertia(
        fn (Assert $page) => $page->where('meta.total', 3)
    );

    $this->get('/doctors?sort=fee')->assertInertia(
        fn (Assert $page) => $page->where('doctors.0.specialty', 'General Medicine')
    );

    // Sunday: Ayesha, Kamal and Nusrat publish hours; Shirin does not.
    $this->get('/doctors?weekdays[]=0')->assertInertia(
        fn (Assert $page) => $page->where('meta.total', 3)
    );
});

it('excludes doctors that are not verified or whose user is archived', function () {
    $user = User::query()->where('email', 'ayesha.rahman@medipath.test')->firstOrFail();
    $user->forceFill(['archived_at' => now()])->save();

    $this->get('/doctors')->assertInertia(fn (Assert $page) => $page->where('meta.total', 3));
});

it('paginates verified doctors at nine per page', function () {
    foreach (range(1, 8) as $index) {
        $user = makeUser([
            'name' => "Extra Doctor {$index}",
            'email' => "extra{$index}@medipath.test",
            'role' => 'doctor',
        ]);
        makeDoctorRecord($user, [
            'doctor_code' => 'DOC-X'.$index,
            'license_number' => 'BMDC-X'.$index,
        ]);
    }

    $this->get('/doctors')->assertInertia(
        fn (Assert $page) => $page->has('doctors', 9)
            ->where('meta.total', 12)
            ->where('meta.last_page', 2)
            ->where('meta.current_page', 1)
    );

    $this->get('/doctors?page=2')->assertInertia(
        fn (Assert $page) => $page->has('doctors', 3)->where('meta.current_page', 2)
    );
});

it('exposes the forced skeleton, empty and error states for QA', function () {
    $this->get('/doctors?state=error')->assertInertia(fn (Assert $page) => $page->where('dataState', 'error'));
    $this->get('/doctors?state=skeleton')->assertInertia(fn (Assert $page) => $page->where('dataState', 'skeleton'));
});

it('keeps the directory public for guests', function () {
    $this->get('/doctors')->assertOk();
});
