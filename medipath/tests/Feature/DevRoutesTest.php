<?php

use Inertia\Testing\AssertableInertia as Assert;

it('hides the design-system gallery outside the local environment', function () {
    $this->get('/dev/ui')->assertNotFound();
    $this->get('/dev/layouts/admin')->assertNotFound();
    $this->get('/dev/frames/360?path=/')->assertNotFound();
});

it('exposes the gallery and responsive harness locally', function () {
    $this->app['env'] = 'local';

    $this->get('/dev/ui')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Dev/DesignSystem')
    );
    $this->get('/dev/layouts/admin')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Dev/LayoutPreview')
    );
    $this->get('/dev/frames/360?path=/')->assertOk()->assertSee('360px viewport', false);
    $this->get('/dev/frames/768?path=/dev/ui')->assertOk();
    $this->get('/dev/frames/1440?path=/')->assertOk();
});

it('rejects unknown harness widths and cross-origin paths', function () {
    $this->app['env'] = 'local';

    $this->get('/dev/frames/999?path=/')->assertNotFound();

    $this->get('/dev/frames/360?path=//evil.example')->assertStatus(422);
    $this->get('/dev/frames/360?path=https://evil.example')->assertStatus(422);
});

it('renders the landing page with seeded data and the server currency symbol', function () {
    $this->seed();

    $this->get('/')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('Guest/Landing')
            ->where('settings.currencySymbol', '৳')
            ->has('featuredDoctors', 3)
    );
});
