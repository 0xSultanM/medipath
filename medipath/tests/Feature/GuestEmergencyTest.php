<?php

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed();
});

it('exposes one-tap emergency contacts, red-flag groups and the live banner', function () {
    $this->get('/emergency')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/Emergency')
            ->has('contacts', 6)
            ->has('groups', 7)
            ->where('dataState', 'success')
            ->where('banner.title', 'Ambulance transfer delay')
            ->etc()
    );
});

it('uses tel links for every call card', function () {
    $response = $this->get('/emergency')->assertOk();
    $contacts = $response->viewData('page')['props']['contacts'];

    foreach ($contacts as $contact) {
        expect($contact['tel'])->toStartWith('tel:');
    }
});

it('supports error and empty states that still provide safe defaults', function () {
    $this->get('/emergency?state=error')->assertInertia(
        fn (Assert $page) => $page->where('dataState', 'error')->has('fallbackContacts', 1)
    );

    $this->get('/emergency?state=empty')->assertInertia(
        fn (Assert $page) => $page->where('dataState', 'empty')->has('fallbackContacts', 1)
    );
});
