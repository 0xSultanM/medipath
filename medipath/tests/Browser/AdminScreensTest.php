<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Part 2 acceptance — admin screens at 360/768/1440:
 * master dashboard, user governance, approval queue preview, doctor CRUD and
 * patient portal-as-patient. Mutations are covered by Pest; these checks cover
 * rendered navigation, dialogs, data-state switching and card conversion.
 */
class AdminScreensTest extends DuskTestCase
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

    public function test_dashboard_renders_kpis_system_health_and_attention(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/dashboard')
                ->waitForText('Master dashboard')
                ->assertSee('PLATFORM KPIS')
                ->assertSee('System overview')
                ->assertSee('Attention queue')
                ->assertSee('COMMISSION REVENUE')
                ->assertSee('PRESCRIPTION COMPLETION RATE')
                ->screenshot('part2-dashboard-1440');

            // Four-state switch: skeleton then back to success.
            $this->clickByText($browser, '.dv-switch button', 'Skeleton');
            $browser->pause(300)->assertPresent('.kpi[aria-busy="true"]');
            $this->clickByText($browser, '.dv-switch button', 'Success');
            $browser->pause(300)->assertSee('COMMISSION REVENUE');

            $browser->resize(360, 1000)->pause(250);
            $this->assertSame(
                true,
                (bool) $browser->script('return document.documentElement.scrollWidth <= window.innerWidth + 1;')[0],
                'Admin dashboard must not overflow horizontally at 360px.'
            );
            $browser->screenshot('part2-dashboard-360');
        });
    }

    public function test_user_management_filters_and_dialogs(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/users')
                ->waitForText('User management')
                ->assertSee('Create internal user')
                ->assertSee('USER DIRECTORY')
                ->screenshot('part2-users-1440');

            $this->clickByText($browser, 'button', 'Create internal user');
            $browser->pause(250)
                ->assertPresent('.modal-stage .modal')
                ->assertSee('Create internal user')
                ->assertSee('Temporary password');
            $browser->screenshot('part2-users-create-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(200);

            $this->clickByText($browser, 'button', 'Change role');
            $browser->pause(250)
                ->assertSee('Change role')
                ->assertSee('Role change is atomic');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(200);

            $browser->resize(360, 1000)->pause(250);
            $rowDisplay = $browser->script("return getComputedStyle(document.querySelector('.tbl tbody tr')).display;")[0];
            $this->assertSame('block', $rowDisplay, 'User table must convert to cards below 640px.');
            $browser->screenshot('part2-users-360');
        });
    }

    public function test_approvals_preview_and_verification_queue(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/approvals')
                ->waitForText('Approvals & verification')
                ->assertSee('Pending sign-ups')
                ->assertSee('Doctor verification')
                ->screenshot('part2-approvals-1440');

            $this->clickByText($browser, 'button', 'Preview credential');
            $browser->pause(500)
                ->assertPresent('.modal-stage iframe')
                ->assertSee('Credential —');
            $browser->screenshot('part2-approvals-credential');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(200);

            $this->clickByText($browser, '.tabs button', 'Doctor verification');
            $browser->pause(600)
                ->assertSee('Approve & verify')
                ->assertSee('Request resubmission');
            $browser->screenshot('part2-approvals-verification');
        });
    }

    public function test_doctor_crud_dialogs_and_soft_archive_copy(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/doctors')
                ->waitForText('Doctor records')
                ->assertSee('Add doctor')
                ->assertSee('Archiving is a soft delete')
                ->screenshot('part2-doctors-1440');

            $this->clickByText($browser, 'button', 'Add doctor');
            $browser->pause(250)
                ->assertSee('License number')
                ->assertSee('License authority')
                ->assertSee('Create doctor');
            $browser->screenshot('part2-doctors-add-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(200);

            $this->clickByText($browser, 'button', 'Archive');
            $browser->pause(250)
                ->assertSee('Type the doctor')
                ->assertSee('appointments')
                ->assertSee('prescriptions');
            $browser->screenshot('part2-doctors-archive-modal');
        });
    }

    public function test_patient_directory_and_as_patient_portal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->admin())
                ->visit('/admin/patients')
                ->waitForText('Patient records')
                ->assertSee('Directory (CRUD)')
                ->assertSee('Patient portal view')
                ->screenshot('part2-patients-1440');

            $this->clickByText($browser, 'button', 'Portal view');
            $browser->pause(700)
                ->assertSee('Portal preview —')
                ->assertSee('read-only')
                ->assertSee('Prescription history')
                ->assertSee('Billing history')
                ->screenshot('part2-patient-portal');

            $browser->resize(360, 1000)->pause(250);
            $this->assertSame(
                true,
                (bool) $browser->script('return document.documentElement.scrollWidth <= window.innerWidth + 1;')[0],
                'Patient portal preview must not overflow horizontally at 360px.'
            );
            $browser->screenshot('part2-patient-portal-360');
        });
    }

    public function test_admin_screens_hold_all_five_breakpoints(): void
    {
        $pages = [
            '/admin/dashboard' => 'Master dashboard',
            '/admin/users' => 'User management',
            '/admin/approvals' => 'Approvals & verification',
            '/admin/doctors' => 'Doctors',
            '/admin/patients' => 'Patients',
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

                    if ($path === '/admin/users') {
                        $browser->screenshot("part2-users-{$width}");
                    }

                    $sidebar = $browser->script("return getComputedStyle(document.querySelector('.side-nav')).visibility;")[0];
                    $this->assertSame($width >= 992 ? 'visible' : 'hidden', $sidebar, "Sidebar breakpoint wrong at {$width}px.");
                });
            }
        }
    }
}
