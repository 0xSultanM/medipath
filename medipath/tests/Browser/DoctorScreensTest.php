<?php

namespace Tests\Browser;

use App\Models\Doctor;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 6 acceptance — verified doctor workspace at five widths. */
class DoctorScreensTest extends DuskTestCase
{
    private function doctor(): User
    {
        return User::query()->where('email', 'ayesha.rahman@medipath.test')->firstOrFail();
    }

    private function firstPatientId(): int
    {
        $doctor = Doctor::query()->whereHas('user', fn ($q) => $q->where('email', 'ayesha.rahman@medipath.test'))->firstOrFail();

        return (int) $doctor->appointments()->value('patient_id');
    }

    private function clickByText(Browser $browser, string $selector, string $text): void
    {
        $browser->script(
            'const el=[...document.querySelectorAll('.json_encode($selector).')].find(n=>n.textContent.includes('.json_encode($text).')); if(el){el.click();}'
        );
    }

    public function test_dashboard_schedule_and_requests(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->doctor())
                ->visit('/doctor/dashboard')
                ->waitForText('Today')
                ->assertSee('DOCTOR KPIS')
                ->assertSee("Today's timeline")
                ->screenshot('part6-dashboard-1440');

            $browser->visit('/doctor/schedule')->waitForText('Weekly schedule')
                ->assertSee('WEEKLY AVAILABILITY')
                ->assertSee('Leave');
            $this->clickByText($browser, 'button', 'Add block');
            $browser->pause(200)->assertSee('Add availability block');
            $browser->screenshot('part6-schedule-block-modal');
            $browser->script("document.querySelector('.modal-x')?.click();");
            $browser->pause(150);

            $browser->visit('/doctor/requests')->waitForText('Appointment requests')
                ->assertSee('REQUESTS QUEUE');
            $this->clickByText($browser, '.tabs button', 'Completed');
            $browser->pause(600)->assertSee('Completed');
            $browser->screenshot('part6-requests-1440');
        });
    }

    public function test_patients_chart_and_prescription_builder(): void
    {
        $patientId = $this->firstPatientId();

        $this->browse(function (Browser $browser) use ($patientId) {
            $browser->resize(1440, 1000)
                ->loginAs($this->doctor())
                ->visit('/doctor/patients')
                ->waitForText('My patients')
                ->assertSee('Open chart')
                ->screenshot('part6-patients-1440');

            $browser->visit("/doctor/patients/{$patientId}")->waitForText('Chart —')
                ->assertSee('CLINICAL HISTORY')
                ->screenshot('part6-patient-chart-1440');

            $browser->visit("/doctor/prescriptions/new?patient={$patientId}")->waitForText('Prescription items')
                ->assertSee('Safety checks')
                ->assertSee('Add medicine');
            $browser->type('#rx-search', 'Amlodipine');
            $browser->pause(700);
            $browser->screenshot('part6-prescription-builder');
        });
    }

    public function test_walkin_reviews_and_settings(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->doctor())
                ->visit('/doctor/walk-in')->waitForText('Walk-in registration')
                ->assertSee('Register walk-in')
                ->screenshot('part6-walkin-1440');

            $browser->visit('/doctor/reviews')->waitForText('My reviews')
                ->assertSee('approved')
                ->screenshot('part6-reviews-1440');

            $browser->visit('/doctor/settings')->waitForText('Profile & settings')
                ->assertSee('Verified credentials')
                ->assertSee('Change password')
                ->screenshot('part6-settings-1440');
        });
    }

    public function test_doctor_screens_hold_all_breakpoints(): void
    {
        $patientId = $this->firstPatientId();
        $pages = [
            '/doctor/dashboard' => 'Today',
            '/doctor/schedule' => 'Weekly schedule',
            '/doctor/requests' => 'Appointment requests',
            '/doctor/patients' => 'My patients',
            "/doctor/patients/{$patientId}" => 'Chart —',
            '/doctor/walk-in' => 'Walk-in registration',
            "/doctor/prescriptions/new?patient={$patientId}" => 'Prescription items',
            '/doctor/reviews' => 'My reviews',
            '/doctor/settings' => 'Profile & settings',
        ];

        foreach ([360, 640, 768, 992, 1440] as $width) {
            foreach ($pages as $path => $heading) {
                $this->browse(function (Browser $browser) use ($width, $path, $heading) {
                    $browser->resize($width, 1000)
                        ->loginAs($this->doctor())
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