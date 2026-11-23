<?php

namespace Database\Seeders;

use App\Models\EmergencyBanner;
use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use Illuminate\Database\Seeder;

class EmergencySeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            ['key' => 'national_emergency', 'label' => 'National emergency', 'number' => '999', 'icon' => '🚨', 'description' => 'Police, fire and ambulance coordination.', 'sort_order' => 1],
            ['key' => 'ambulance', 'label' => 'Ambulance / health line', 'number' => '16263', 'icon' => '🚑', 'description' => 'Shastho Batayon health advice and ambulance dispatch.', 'sort_order' => 2],
            ['key' => 'clinic_desk', 'label' => 'Clinic emergency desk', 'number' => '+880 2 5566 7788', 'icon' => '🏥', 'description' => 'MediPath Central Clinic, 24-hour reception.', 'sort_order' => 3],
            ['key' => 'poison_control', 'label' => 'Poison information', 'number' => '16789', 'icon' => '☠️', 'description' => 'National poison information centre.', 'sort_order' => 4],
            ['key' => 'women_children', 'label' => 'Women & children helpline', 'number' => '109', 'icon' => '👨‍👩‍👧', 'description' => 'Confidential support for women and children.', 'sort_order' => 5],
            ['key' => 'mental_health', 'label' => 'Mental health support', 'number' => '09612 333 444', 'icon' => '🧠', 'description' => 'Crisis counselling (Kaan Pete Roi).', 'sort_order' => 6],
        ];

        foreach ($contacts as $contact) {
            EmergencyContact::query()->updateOrCreate(['key' => $contact['key']], [...$contact, 'is_active' => true]);
        }

        $rules = [
            [
                'category' => 'Cardiac',
                'title' => 'Chest pain or pressure, especially with sweating or nausea',
                'keywords' => ['chest pain', 'chest tightness', 'crushing chest', 'left arm pain', 'jaw pain', 'sweating with chest pain'],
                'action_directive' => 'Call 999 now. Sit down, stop activity, and do not drive yourself. If the person is unconscious and not breathing, start CPR if trained.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 1,
            ],
            [
                'category' => 'Respiratory',
                'title' => 'Severe breathlessness, choking or blue lips',
                'keywords' => ['cannot breathe', 'severe shortness of breath', 'gasping', 'choking', 'blue lips', 'unable to speak in full sentences'],
                'action_directive' => 'Call 999 and keep the person upright. For choking, follow the choking algorithm. Do not wait for an online reply.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 2,
            ],
            [
                'category' => 'Stroke',
                'title' => 'Face drooping, arm weakness or slurred speech (FAST)',
                'keywords' => ['face drooping', 'arm weakness', 'speech slurred', 'sudden vision loss', 'fast stroke', 'one side weakness'],
                'action_directive' => 'Call 999 immediately and note the time symptoms started. Do not give food or drink. Stroke treatment is time-critical.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 3,
            ],
            [
                'category' => 'Bleeding',
                'title' => 'Uncontrolled bleeding or vomiting blood',
                'keywords' => ['severe bleeding', 'blood vomiting', 'coughing blood', 'uncontrolled bleeding', 'black stools with weakness'],
                'action_directive' => 'Call 999. Apply firm direct pressure to external bleeding with a clean cloth. Do not remove an object that is impaled.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 4,
            ],
            [
                'category' => 'Anaphylaxis',
                'title' => 'Throat or tongue swelling, or hives with breathing difficulty',
                'keywords' => ['throat swelling', 'tongue swelling', 'anaphylaxis', 'hives with breathing difficulty', 'severe allergic reaction'],
                'action_directive' => 'Use an adrenaline auto-injector if available, then call 999. Lay the person flat with legs raised unless breathing is easier upright.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 5,
            ],
            [
                'category' => 'Pediatric',
                'title' => 'Unresponsive child, seizure, or fever in a young infant',
                'keywords' => ['child unresponsive', 'seizure in child', 'infant fever', 'dehydration no urine', 'baby not feeding'],
                'action_directive' => 'Call 999 for an unresponsive child or a seizure lasting over 5 minutes. Do not leave the child alone.',
                'hotline_key' => 'national_emergency',
                'sort_order' => 6,
            ],
            [
                'category' => 'Crisis',
                'title' => 'Thoughts of self-harm, overdose or immediate danger',
                'keywords' => ['suicidal', 'self harm', 'overdose', 'want to die', 'harm myself'],
                'action_directive' => 'Call the mental health support line or 999 if there is immediate danger. Stay with the person and remove access to means.',
                'hotline_key' => 'mental_health',
                'sort_order' => 7,
            ],
        ];

        foreach ($rules as $rule) {
            $keywords = $rule['keywords'];
            unset($rule['keywords']);

            EmergencyRule::query()->updateOrCreate(
                ['category' => $rule['category'], 'title' => $rule['title']],
                [...$rule, 'symptom_keywords' => $keywords, 'severity' => 'critical', 'is_active' => true],
            );
        }

        // No always-on banner by default; admins schedule one in Phase 3.
        EmergencyBanner::query()->updateOrCreate(
            ['title' => 'Monsoon clinic hours notice'],
            [
                'message' => 'During heavy rain the clinic opens at 10:00. Emergency lines remain 24/7.',
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => false,
            ],
        );

        // Time-bounded demo notice used by the public emergency page.
        EmergencyBanner::query()->updateOrCreate(
            ['title' => 'Ambulance transfer delay'],
            [
                'message' => 'Ring Road flooding is delaying ambulance transfers. Expect longer ER travel times today.',
                'starts_at' => now()->subHours(6),
                'ends_at' => now()->addHours(18),
                'is_active' => true,
            ],
        );
    }
}
