<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** Part 5 acceptance — two-stage triage, fallback badge and emergency halt. */
class PatientTriageTest extends DuskTestCase
{
    private function patient(): User
    {
        return User::query()->where('email', 'patient@medipath.test')->firstOrFail();
    }

    private function clickByText(Browser $browser, string $text): void
    {
        $browser->script(
            'const el=[...document.querySelectorAll("button")].find(n=>n.textContent.includes('.json_encode($text).')); if(el){el.click();}'
        );
    }

    public function test_guidance_and_emergency_paths(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1440, 1000)
                ->loginAs($this->patient())
                ->visit('/patient/symptom-checker')
                ->waitForText('AI Symptom Checker')
                ->assertSee('Stage 1: deterministic red-flag engine')
                ->type('#triage-symptoms', 'Sore throat and fever for three days; swallowing is painful.')
                ->check('#triage-consent');

            $this->clickByText($browser, 'Run symptom check');
            $browser->waitForText('Questions to ask your doctor')
                ->assertSee('Recommended specialty')
                ->assertSee('deterministic fallback')
                ->assertSee('Recommended specialty');
            $browser->script("document.querySelector('.dv-host')?.scrollIntoView({block:'start'});");
            $browser->pause(200)->screenshot('part5-triage-guidance');

            $browser->visit('/patient/symptom-checker')
                ->waitForText('AI Symptom Checker')
                ->type('#triage-symptoms', 'I have crushing chest pain spreading to my left arm.')
                ->check('#triage-consent');

            $this->clickByText($browser, 'Run symptom check');
            $browser->waitForText('Red flag detected')
                ->assertSee('Call one of these immediately')
                ->assertSee('While you wait')
                ->screenshot('part5-triage-emergency');

        });
    }
}