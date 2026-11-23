<?php

namespace Tests\Browser;

use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 9 — cross-portal 1024 layout + keyboard accessibility checks. */
class IntegrationPolishTest extends DuskTestCase
{
    private function portalUser(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    public function test_every_portal_holds_at_1024(): void
    {
        $portals = [
            'admin@medipath.test' => ['/admin/dashboard', 'Master dashboard'],
            'ayesha.rahman@medipath.test' => ['/doctor/dashboard', "Today's timeline"],
            'pharmacist@medipath.test' => ['/pharmacist/dashboard', 'Oldest pending'],
            'patient@medipath.test' => ['/patient/dashboard', 'Quick actions'],
        ];

        foreach ($portals as $email => [$path, $heading]) {
            $this->browse(function (Browser $browser) use ($email, $path, $heading) {
                $browser->resize(1024, 1000)
                    ->loginAs($this->portalUser($email))
                    ->visit($path)
                    ->waitForText($heading);

                $this->assertSame(
                    true,
                    (bool) $browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1;')[0],
                    "{$path} must not overflow at 1024px."
                );

                $visibility = $browser->script("return getComputedStyle(document.querySelector('.side-nav')).visibility;")[0];
                $this->assertSame('visible', $visibility, "{$path} must keep the fixed sidebar at 1024px.");

                $browser->screenshot('part9-'.str_replace('/', '-', trim(parse_url($path, PHP_URL_PATH) ?? 'portal', '/')).'-1024');
            });
        }
    }

    public function test_skip_link_modal_focus_and_escape(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->portalUser('pharmacist@medipath.test'))
                ->visit('/pharmacist/dashboard')
                ->waitForText('Oldest pending');

            $browser->driver->findElement(WebDriverBy::tagName('body'))->sendKeys(WebDriverKeys::TAB);
            $focused = $browser->script('return document.activeElement?.className || "";')[0];
            $this->assertStringContainsString('skip-link', $focused, 'The skip link must be the first tab stop.');
            $browser->screenshot('part9-skip-link-focus');

            $browser->visit('/pharmacist/inventory')->waitForText('Medicine inventory');
            $browser->script("const b=[...document.querySelectorAll('button')].find(n=>n.textContent.includes('+ Add medicine')); b && b.click();");
            $browser->pause(300)->assertSee('Add medicine');

            $inside = $browser->script('const modal=document.querySelector(".modal"); return Boolean(modal && modal.contains(document.activeElement));')[0];
            $this->assertSame(true, $inside, 'Focus must move inside the modal.');

            $browser->driver->findElement(WebDriverBy::tagName('body'))->sendKeys(WebDriverKeys::ESCAPE);
            $browser->pause(300);
            $closed = $browser->script('return !document.querySelector(".modal");')[0];
            $this->assertSame(true, $closed, 'Escape must close the modal.');
        });
    }
}