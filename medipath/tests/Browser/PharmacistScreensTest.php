<?php

namespace Tests\Browser;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 8 acceptance — pharmacy queue, gate, dispense, inventory, invoices, workflow. */
class PharmacistScreensTest extends DuskTestCase
{
    private function pharmacist(): User
    {
        return User::query()->where('email', 'pharmacist@medipath.test')->firstOrFail();
    }

    private function pendingId(): int
    {
        return (int) Prescription::query()->where('status', PrescriptionStatus::PendingDispense)->value('id');
    }

    private function receiptId(): int
    {
        return (int) Prescription::query()->whereHas('invoice')->value('id');
    }

    private function clickByText(Browser $browser, string $selector, string $text): void
    {
        $browser->script(
            'const el=[...document.querySelectorAll('.json_encode($selector).')].find(n=>n.textContent.includes('.json_encode($text).')); if(el){el.click();}'
        );
    }

    public function test_dashboard_queue_and_gate(): void
    {
        $pendingId = $this->pendingId();

        $this->browse(function (Browser $browser) use ($pendingId) {
            $browser->resize(1440, 1000)
                ->loginAs($this->pharmacist())
                ->visit('/pharmacist/dashboard')
                ->waitForText('Pharmacy dashboard')
                ->assertSee('PHARMACY KPIS')
                ->assertSee('Oldest pending')
                ->assertSee('Urgent stock queue')
                ->screenshot('part8-dashboard-1440');

            $browser->visit('/pharmacist/prescriptions')
                ->waitForText('Pending prescriptions')
                ->assertSee('PENDING PRESCRIPTIONS')
                ->assertSee('Review & dispense')
                ->screenshot('part8-pending-1440');

            $browser->visit("/pharmacist/prescriptions/{$pendingId}")
                ->waitForText('Safety gate —')
                ->assertSee('Pharmacist verification')
                ->assertSee('Proceed to dispense & bill')
                ->screenshot('part8-safety-gate-1440');
        });
    }

    public function test_dispense_builder_and_receipt(): void
    {
        $pendingId = $this->pendingId();
        $receiptId = $this->receiptId();

        $this->browse(function (Browser $browser) use ($pendingId, $receiptId) {
            $browser->resize(1440, 1000)
                ->loginAs($this->pharmacist())
                ->visit("/pharmacist/prescriptions/{$pendingId}/dispense")
                ->waitForText('Itemised dispense')
                ->assertSee('Live totals')
                ->assertSee('Final gate')
                ->screenshot('part8-dispense-1440');

            $browser->visit("/pharmacist/prescriptions/{$receiptId}/dispense")
                ->waitForText('Dispensed & billed')
                ->assertSee('Invoice PDF')
                ->assertSee('What just happened')
                ->screenshot('part8-receipt-1440');
        });
    }

    public function test_inventory_modals_and_alerts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->pharmacist())
                ->visit('/pharmacist/inventory')
                ->waitForText('Medicine inventory')
                ->assertSee('Recent stock ledger')
                ->screenshot('part8-inventory-1440');

            $this->clickByText($browser, 'button', '+ Add medicine');
            $browser->pause(250)->assertSee('Add medicine');
            $browser->screenshot('part8-inventory-add-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $this->clickByText($browser, 'button', 'Restock');
            $browser->pause(250)->assertSee('Current stock');
            $browser->screenshot('part8-inventory-restock-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/pharmacist/alerts')
                ->waitForText('Alerts console')
                ->assertSee('ALERT TIERS');
            $this->clickByText($browser, '.tabs button', 'Expired');
            $browser->pause(300)->assertSee('Replace batch');
            $browser->screenshot('part8-alerts-1440');
        });
    }

    public function test_invoices_workflow_settings_and_notifications(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->pharmacist())
                ->visit('/pharmacist/invoices')
                ->waitForText('Invoices')
                ->assertSee('BILLED TODAY')
                ->screenshot('part8-invoices-1440');

            $this->clickByText($browser, 'button', 'Status…');
            $browser->pause(250)->assertSee('Payment status —');
            $browser->screenshot('part8-invoice-status-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/pharmacist/workflow?q=PT-1001')
                ->waitForText('Registration')
                ->assertSee('Workflow tracker')
                ->assertSee('Discharged')
                ->screenshot('part8-workflow-1440');

            $browser->visit('/pharmacist/notifications')
                ->waitForText('unread of')
                ->assertSee('Mark all read')
                ->screenshot('part8-notifications-1440');

            $browser->visit('/pharmacist/settings')
                ->waitForText('Profile & settings')
                ->assertSee('Approved credentials')
                ->assertSee('Change password')
                ->screenshot('part8-settings-1440');
        });
    }

    public function test_pharmacist_screens_hold_all_breakpoints(): void
    {
        $pendingId = $this->pendingId();
        $receiptId = $this->receiptId();
        $pages = [
            '/pharmacist/dashboard' => 'Pharmacy dashboard',
            '/pharmacist/prescriptions' => 'Pending prescriptions',
            "/pharmacist/prescriptions/{$pendingId}" => 'Safety gate —',
            "/pharmacist/prescriptions/{$receiptId}/dispense" => 'Dispensed & billed',
            '/pharmacist/inventory' => 'Medicine inventory',
            '/pharmacist/alerts' => 'Alerts console',
            '/pharmacist/invoices' => 'BILLED TODAY',
            '/pharmacist/workflow?q=PT-1001' => 'Registration',
            '/pharmacist/notifications' => 'unread of',
            '/pharmacist/settings' => 'Profile & settings',
        ];

        foreach ([360, 640, 768, 992, 1440] as $width) {
            foreach ($pages as $path => $heading) {
                $this->browse(function (Browser $browser) use ($width, $path, $heading) {
                    $browser->resize($width, 1000)
                        ->loginAs($this->pharmacist())
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