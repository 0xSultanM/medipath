<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Part 3 acceptance — appointments, records, reports, activity, knowledge,
 * safety, settings, data and security at 360/640/768/992/1440.
 */
class AdminAdvancedScreensTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::query()->where('email', 'admin@medipath.test')->firstOrFail();
    }

    private function clickByText(Browser $browser, string $selector, string $text): void
    {
        $browser->script(
            'const el=[...document.querySelectorAll('.json_encode($selector).')].find(n=>n.textContent.includes('.json_encode($text).')); if(el){el.click();}'
        );
    }

    public function test_appointments_records_and_reports(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/appointments')
                ->waitForText('Global appointments')
                ->assertSee('Appointments')
                ->screenshot('part3-appointments-1440');

            $this->clickByText($browser, 'button', 'Cancel on behalf');
            $browser->pause(250)
                ->assertSee('Reason (required)')
                ->assertSee('both parties are notified');
            $browser->screenshot('part3-appointments-cancel-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/admin/records')->waitForText('Clinical & billing records');
            $this->clickByText($browser, '.tabs button', 'Billing (invoices)');
            $browser->pause(600)->assertSee('Invoice');
            $this->clickByText($browser, '.tabs button', 'Inventory');
            $browser->pause(600)->assertSee('STOCK STATUS');
            $browser->screenshot('part3-records-inventory');

            $browser->visit('/admin/reports')->waitForText('Reports & analytics')
                ->assertSee('Monthly revenue')
                ->assertSee('Appointment conversion')
                ->assertSee('Top-doctor leaderboard')
                ->assertSee('CSV')
                ->assertSee('PDF');
            $browser->screenshot('part3-reports-1440');
        });
    }

    public function test_activity_knowledge_and_safety(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/activity')
                ->waitForText('Activity log')
                ->assertSee('append-only')
                ->assertSee('LOG ENTRIES');
            $browser->screenshot('part3-activity-1440');

            $this->clickByText($browser, 'button', 'View JSON');
            $browser->pause(250);
            if ($browser->element('.modal-stage')) {
                $browser->assertSee('Audit entry');
                $browser->script("document.querySelector('.modal-x')?.click();");
                $browser->pause(150);
            }

            $browser->visit('/admin/faq-reviews')->waitForText('Knowledge base & moderation')
                ->assertSee('Chatbot FAQs')
                ->assertSee('Review moderation');
            $this->clickByText($browser, 'button', 'New FAQ');
            $browser->pause(250)->assertSee('Save & reindex');
            $browser->screenshot('part3-faq-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $this->clickByText($browser, '.tabs button', 'Review moderation');
            $browser->pause(600)->assertSee('Approve')->assertSee('Hide & reject');
            $this->clickByText($browser, 'button', 'Hide & reject');
            $browser->pause(250)->assertSee('Moderation note');
            $browser->screenshot('part3-review-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/admin/safety')->waitForText('Clinical safety console')
                ->assertSee('Emergency contacts')
                ->assertSee('Red-flag rules');
            $this->clickByText($browser, '[aria-label="Emergency contacts"] button', 'Edit');
            $browser->pause(250)->assertSee('Edit emergency contact');
            $browser->screenshot('part3-safety-contact-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $this->clickByText($browser, '.tabs button', 'Symptom → specialty mapping');
            $browser->pause(600)->assertSee('MAPPED SPECIALTY');
            $browser->screenshot('part3-safety-mapping');
        });
    }

    public function test_settings_data_and_security(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/settings')
                ->waitForText('Global system settings')
                ->assertSee('Clinic identity')
                ->assertSee('Revenue commission')
                ->assertSee('IMPACT PREVIEW')
                ->screenshot('part3-settings-1440');

            $browser->visit('/admin/data')->waitForText('Data management')
                ->assertSee('CSV exports')
                ->assertSee('Backup snapshots')
                ->assertSee('Danger zone');
            $this->clickByText($browser, 'button', 'Create snapshot now');
            $browser->pause(250)->assertSee('Create backup snapshot');
            $browser->screenshot('part3-data-backup-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $this->clickByText($browser, 'button', 'Delete failed backup files');
            $browser->pause(250)->assertSee('Re-enter your admin password')->assertSee('DELETE');
            $browser->screenshot('part3-data-danger-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/admin/security')->waitForText('Admin profile & security')
                ->assertSee('Change password')
                ->assertSee('ACTIVE SESSIONS')
                ->assertSee('Revoke all other sessions');
            $browser->screenshot('part3-security-1440');
        });
    }

    public function test_part_three_screens_hold_all_breakpoints(): void
    {
        $pages = [
            '/admin/appointments' => 'Global appointments',
            '/admin/records' => 'Clinical & billing records',
            '/admin/reports' => 'Reports & analytics',
            '/admin/activity' => 'Activity log',
            '/admin/faq-reviews' => 'Knowledge base & moderation',
            '/admin/safety' => 'Clinical safety console',
            '/admin/settings' => 'Global system settings',
            '/admin/data' => 'Data management',
            '/admin/security' => 'Admin profile & security',
        ];

        foreach ([360, 640, 768, 992, 1440] as $width) {
            foreach ($pages as $path => $heading) {
                $this->browse(function (Browser $browser) use ($width, $path, $heading) {
                    $browser->resize($width, 1000)
                        ->loginAs($this->admin())
                        ->visit($path)
                        ->waitForText($heading);

                    $this->assertSame(
                        true,
                        (bool) $browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1;')[0],
                        "{$path} must not overflow horizontally at {$width}px."
                    );
                });
            }
        }
    }
}