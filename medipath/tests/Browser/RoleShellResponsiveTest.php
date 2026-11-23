<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Part 0 acceptance: shells at 360 / 768 / 1440, drawer conversions, the
 * table→card threshold, theme toggle and notification drawer.
 *
 * Prerequisites: `php artisan migrate:fresh --seed` against the Dusk database.
 */
class RoleShellResponsiveTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::query()->where('email', 'admin@medipath.test')->firstOrFail();
    }

    public function test_admin_shell_and_navigation_at_three_widths(): void
    {
        foreach ([360, 768, 1440] as $width) {
            $this->browse(function (Browser $browser) use ($width) {
                $browser->resize($width, 1000)
                    ->loginAs($this->admin())
                    ->visit('/admin/dashboard')
                    ->waitForText('Master dashboard')
                    ->assertSee('MediPath')
                    ->screenshot("part0-admin-shell-{$width}");

                $sidebarVisibility = $browser->script(
                    "return getComputedStyle(document.querySelector('.side-nav')).visibility;"
                )[0];
                $burgerDisplay = $browser->script(
                    "return getComputedStyle(document.querySelector('.burger.b-role')).display;"
                )[0];

                if ($width < 992) {
                    $this->assertSame('hidden', $sidebarVisibility, 'Sidebar must be an off-canvas drawer below 992.');
                    $this->assertSame('grid', $burgerDisplay, 'Burger must be visible below 992.');
                } else {
                    $this->assertSame('visible', $sidebarVisibility, 'Sidebar must be fixed at ≥992.');
                    $this->assertSame('none', $burgerDisplay, 'Burger must be hidden at ≥992.');
                }
            });
        }
    }

    public function test_table_converts_to_cards_below_640(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(360, 1000)
                ->visit('/dev/ui')
                ->waitForText('Component library')
                ->assertSee('All appointments');

            $rowDisplay = $browser->script(
                "return getComputedStyle(document.querySelector('.tbl tbody tr')).display;"
            )[0];
            $this->assertSame('block', $rowDisplay, 'Rows must become cards below 640.');
            $browser->script("document.querySelector('#table-title').scrollIntoView({block:'start'});");
            $browser->pause(250);
            $browser->screenshot('part0-table-card-360');

            $browser->resize(768, 1000);
            $browser->pause(200);
            $rowDisplay = $browser->script(
                "return getComputedStyle(document.querySelector('.tbl tbody tr')).display;"
            )[0];
            $this->assertSame('table-row', $rowDisplay, 'Rows must be a real table at ≥640.');
            $browser->script("document.querySelector('#table-title').scrollIntoView({block:'start'});");
            $browser->pause(250);
            $browser->screenshot('part0-table-768');
        });
    }

    public function test_theme_toggle_changes_both_theme_contracts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->visit('/')
                ->waitForText('Featured verified doctors');

            $before = $browser->script('return document.documentElement.dataset.theme;')[0];
            $browser->script("document.querySelector('.pub-nav .switch input').click();");
            $browser->pause(300);
            $after = $browser->script('return document.documentElement.dataset.theme;')[0];
            $bsAfter = $browser->script('return document.documentElement.dataset.bsTheme;')[0];

            $this->assertNotSame($before, $after, 'Theme toggle must flip the rendered mode.');
            $this->assertSame($after, $bsAfter, 'data-bs-theme must stay wired to data-theme.');
            $browser->screenshot('part0-theme-toggle');
        });
    }

    public function test_mobile_drawers_bell_and_filter(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(360, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/dashboard')
                ->waitForText('Master dashboard');

            $browser->script("document.querySelector('.bell').click();");
            $browser->pause(300);
            $browser->assertPresent('.drawer--notif.is-open');
            $browser->screenshot('part0-notification-drawer-360');

            $browser->script("document.querySelector('.drawer--notif .x-close')?.click();");
            $browser->pause(250);

            $browser->visit('/dev/ui')->waitForText('Component library');
            $browser->script(
                "const btn=[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Filters')); btn && btn.click();"
            );
            $browser->pause(300);
            $browser->assertPresent('.drawer--filters.is-open');
            $browser->screenshot('part0-filter-drawer-360');
        });
    }
}
