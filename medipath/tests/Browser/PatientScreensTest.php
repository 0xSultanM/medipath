<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 4 acceptance — patient portal screens at 360/640/768/992/1440. */
class PatientScreensTest extends DuskTestCase
{
    private function patient(): User
    {
        return User::query()->where('email', 'patient@medipath.test')->firstOrFail();
    }

    private function clickByText(Browser $browser, string $selector, string $text): void
    {
        $browser->script(
            'const el=[...document.querySelectorAll('.json_encode($selector).')].find(n=>n.textContent.includes('.json_encode($text).')); if(el){el.click();}'
        );
    }

    public function test_dashboard_appointments_and_booking(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->patient())
                ->visit('/patient/dashboard')
                ->waitForText('Quick actions')
                ->assertSee('DATA VIEW · PATIENT KPIS')
                ->assertSee('UPCOMING VISITS')
                ->screenshot('part4-dashboard-1440');

            $this->clickByText($browser, 'button', 'Cancel');
            $browser->pause(200);
            if ($browser->element('.modal-stage')) {
                $browser->assertSee('Cancel this appointment?');
                $browser->screenshot('part4-dashboard-cancel-modal');
                $browser->script("document.querySelector('.modal-x')?.click();");
                $browser->pause(150);
            }

            $browser->visit('/patient/appointments')->waitForText('My appointments')
                ->assertSee('APPOINTMENT HISTORY');
            $this->clickByText($browser, '.tabs button', 'Completed');
            $browser->pause(500)->assertSee('Completed');
            $browser->screenshot('part4-appointments-1440');

            $browser->visit('/patient/book/DOC-1001')->waitForText('Booking summary')
                ->assertSee('AVAILABLE SLOTS')
                ->assertSee('Confirm booking request');
            $browser->screenshot('part4-booking-1440');
        });
    }

    public function test_prescriptions_bills_and_clinical_profile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->patient())
                ->visit('/patient/prescriptions')
                ->waitForText('My prescriptions')
                ->assertSee('OWNER-ONLY')
                ->screenshot('part4-prescriptions-1440');

            $browser->visit('/patient/bills')->waitForText('My bills')
                ->assertSee('OUTSTANDING')
                ->assertSee('Search by invoice ID');
            $browser->screenshot('part4-bills-1440');

            $browser->visit('/patient/clinical-profile')->waitForText('Allergies & current medications')
                ->assertSee('Add allergy');
            $this->clickByText($browser, 'button', 'Add allergy');
            $browser->pause(200)->assertSee('Save allergy');
            $browser->screenshot('part4-allergy-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $this->clickByText($browser, '.tabs button', 'Current medications');
            $browser->pause(500)->assertSee('Add medication');
        });
    }

    public function test_reviews_notifications_settings_and_assistant(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->patient())
                ->visit('/patient/reviews')->waitForText('Reviews')
                ->assertSee('Leave a review')
                ->screenshot('part4-reviews-1440');

            $browser->visit('/patient/notifications')->waitForText('Notifications')
                ->assertSee('Mark all read');
            $browser->screenshot('part4-notifications-1440');

            $browser->visit('/patient/settings')->waitForText('Profile & settings')
                ->assertSee('Immutable identifiers')
                ->assertSee('Change password')
                ->assertSee('Preferences');
            $browser->screenshot('part4-settings-1440');

            $browser->visit('/patient/symptom-checker')->waitForText('AI Symptom Checker')
                ->assertSee('Describe your symptoms')
                ->assertSee('Stage 1: deterministic red-flag engine');
            $browser->screenshot('part4-symptom-checker-1440');
        });
    }

    public function test_patient_screens_hold_all_breakpoints(): void
    {
        $pages = [
            '/patient/dashboard' => 'Quick actions',
            '/patient/doctors' => 'Find doctors',
            '/patient/appointments' => 'My appointments',
            '/patient/prescriptions' => 'My prescriptions',
            '/patient/bills' => 'My bills',
            '/patient/clinical-profile' => 'Allergies & current medications',
            '/patient/reviews' => 'Reviews',
            '/patient/notifications' => 'Notifications',
            '/patient/symptom-checker' => 'AI Symptom Checker',
            '/patient/settings' => 'Profile & settings',
        ];

        foreach ([360, 640, 768, 992, 1440] as $width) {
            foreach ($pages as $path => $heading) {
                $this->browse(function (Browser $browser) use ($width, $path, $heading) {
                    $browser->resize($width, 1000)
                        ->loginAs($this->patient())
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