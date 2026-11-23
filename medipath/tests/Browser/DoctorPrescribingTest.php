<?php

namespace Tests\Browser;

use App\Models\Doctor;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 7 acceptance — prescription history, AI safety review, notifications. */
class DoctorPrescribingTest extends DuskTestCase
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

    public function test_prescription_history_and_notifications(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->doctor())
                ->visit('/doctor/prescriptions')
                ->waitForText('Prescriptions')
                ->assertSee('PRESCRIPTION HISTORY')
                ->assertSee('only prescriptions you wrote')
                ->screenshot('part7-prescriptions-1440');

            $browser->visit('/doctor/notifications')
                ->waitForText('unread of')
                ->assertSee('Notifications')
                ->assertSee('Mark all read')
                ->screenshot('part7-notifications-1440');
        });
    }

    public function test_ai_safety_summary_is_labelled_and_advisory(): void
    {
        $patientId = $this->firstPatientId();

        $this->browse(function (Browser $browser) use ($patientId) {
            $browser->resize(1440, 1000)
                ->loginAs($this->doctor())
                ->visit("/doctor/prescriptions/new?patient={$patientId}")
                ->waitForText('Prescription items')
                ->assertSee('AI safety summary')
                ->assertSee('Deterministic checks above stay authoritative');

            $browser->type('#rx-search', 'Amlodipine')->pause(800);
            $this->clickByText($browser, '.list-quiet button', 'Add');
            $browser->pause(800);

            $this->clickByText($browser, 'button', 'Generate summary');
            $browser->waitForText('Deterministic fallback', 10);
            $browser->assertSee('advisory')
                ->assertSee('Source: deterministic_fallback');
            $browser->script("const h=[...document.querySelectorAll('h3')].find(n=>n.textContent.includes('AI safety summary')); h?.scrollIntoView({block:'center'});");
            $browser->pause(300)->screenshot('part7-ai-safety-summary');
        });
    }

    public function test_part_seven_screens_hold_all_breakpoints(): void
    {
        $pages = [
            '/doctor/prescriptions' => 'Prescriptions',
            '/doctor/notifications' => 'unread of',
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