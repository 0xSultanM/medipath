<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Phase 1 visual acceptance at 360 / 768 / 1440. Requires seeded data on the
 * configured Dusk database and the app served on :8000.
 */
class GuestScreensTest extends DuskTestCase
{
    private function assertNoHorizontalOverflow(Browser $browser, string $where): void
    {
        $fits = $browser->script('return document.body.scrollWidth <= window.innerWidth + 1;')[0];
        $this->assertTrue((bool) $fits, "Horizontal overflow detected on {$where}");
    }

    public function test_landing_at_three_widths(): void
    {
        foreach ([360, 768, 1440] as $width) {
            $this->browse(function (Browser $browser) use ($width) {
                $browser->logout()->resize($width, 1000)
                    ->visit('/')
                    ->waitForText('Featured verified doctors')
                    ->assertSee('Find a verified doctor')
                    ->assertSee('Emergency')
                    ->assertPresent('.bot-fab');

                $this->assertCount(3, $browser->elements('.doc-card'));
                $this->assertNoHorizontalOverflow($browser, "landing {$width}");

                if ($width < 768) {
                    $this->assertSame('grid', $browser->script(
                        "return getComputedStyle(document.querySelector('.pub-nav .burger')).display;"
                    )[0]);
                } else {
                    $this->assertSame('none', $browser->script(
                        "return getComputedStyle(document.querySelector('.pub-nav .burger')).display;"
                    )[0]);
                }

                $browser->screenshot("part1-landing-{$width}");
            });
        }
    }

    public function test_directory_mobile_filter_drawer_and_profile_availability(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()->resize(360, 1000)
                ->visit('/doctors')
                ->waitForText('verified doctors')
                ->assertPresent('.doc-card');

            $this->assertNoHorizontalOverflow($browser, 'directory 360');
            $browser->screenshot('part1-directory-360');

            $browser->script(
                "const btn=[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Filters')); btn && btn.click();"
            );
            $browser->pause(300);
            $browser->assertPresent('.drawer--filters.is-open');
            $browser->screenshot('part1-directory-filters-360');

            $browser->script("document.querySelector('.drawer--filters .x-close')?.click();");
            $browser->pause(250);

            $browser->visit('/doctors/DOC-1001')
                ->waitForText('Availability')
                ->assertSee('Dr. Ayesha Rahman');

            $this->assertNoHorizontalOverflow($browser, 'profile 360');
            $this->assertSame('block', $browser->script(
                "return getComputedStyle(document.querySelector('.tbl tbody tr')).display;"
            )[0]);
            $browser->screenshot('part1-profile-360');

            $browser->resize(768, 1000);
            $browser->pause(250);
            $this->assertSame('table-row', $browser->script(
                "return getComputedStyle(document.querySelector('.tbl tbody tr')).display;"
            )[0]);
            $browser->screenshot('part1-profile-768');

            $browser->resize(1440, 1000);
            $browser->pause(250);
            $browser->visit('/doctors')->waitForText('verified doctors');
            $columns = $browser->script(
                "return getComputedStyle(document.querySelector('.doctor-grid')).gridTemplateColumns.split(' ').length;"
            )[0];
            $this->assertSame(3, (int) $columns, 'Doctor grid must use 3 columns at 1440.');
            $browser->screenshot('part1-directory-1440');
            $browser->visit('/doctors/DOC-1001')->waitForText('Availability');
            $browser->screenshot('part1-profile-1440');
        });
    }

    public function test_phase_one_screens_at_all_widths(): void
    {
        foreach ([360, 768, 1440] as $width) {
            $this->browse(function (Browser $browser) use ($width) {
                $browser->logout()->resize($width, 1000)
                    ->visit('/emergency')
                    ->waitForText('Call, one tap')
                    ->assertSee('Red flags');
                $this->assertNoHorizontalOverflow($browser, "emergency {$width}");
                $browser->screenshot("part1-emergency-{$width}");

                $browser->visit('/login')->waitForText('Welcome back')->assertSee('Sign in');
                $this->assertNoHorizontalOverflow($browser, "login {$width}");
                $browser->screenshot("part1-login-{$width}");

                $browser->visit('/register')->waitForText('Create your account')->assertSee('Patient');
                $this->assertNoHorizontalOverflow($browser, "register {$width}");
                $browser->screenshot("part1-register-{$width}");

                $browser->visit('/forgot-password')->waitForText('Reset your password');
                $this->assertNoHorizontalOverflow($browser, "forgot {$width}");
                $browser->screenshot("part1-forgot-{$width}");
            });
        }
    }

    public function test_emergency_page_and_auth_screens(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()->resize(360, 1000)
                ->visit('/emergency')
                ->waitForText('Call, one tap')
                ->assertSee('Red flags')
                ->assertSee('Ambulance transfer delay');

            $this->assertCount(6, $browser->elements('.call-card'));
            $this->assertNoHorizontalOverflow($browser, 'emergency 360');
            $browser->screenshot('part1-emergency-360');

            $browser->visit('/login')->waitForText('Welcome back')->assertSee('Show');
            $browser->screenshot('part1-login-360');

            $browser->visit('/register')->waitForText('Create your account')->assertSee('Patient');
            $this->assertCount(3, $browser->elements('.role-pick'));
            $this->assertNoHorizontalOverflow($browser, 'register 360');
            $browser->screenshot('part1-register-360');

            $browser->visit('/forgot-password')->waitForText('Reset your password');
            $browser->screenshot('part1-forgot-360');
        });
    }

    public function test_pending_holding_screen_and_mobile_nav(): void
    {
        $pending = User::query()->where('email', 'tanvir.alam@medipath.test')->firstOrFail();

        $this->browse(function (Browser $browser) use ($pending) {
            $browser->resize(360, 1000)
                ->loginAs($pending)
                ->visit('/pending')
                ->waitForText('with the clinic admin')
                ->assertSee('Pending review')
                ->assertSee('Check status')
                ->assertSee('Queue position');

            $this->assertCount(3, $browser->elements('.stp'));
            $this->assertNoHorizontalOverflow($browser, 'pending 360');
            $browser->screenshot('part1-pending-360');
        });
    }
}
