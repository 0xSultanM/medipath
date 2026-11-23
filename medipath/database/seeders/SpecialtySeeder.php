<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['name' => 'Cardiology', 'icon' => '❤️', 'description' => 'Heart and circulatory conditions, including hypertension and arrhythmia.'],
            ['name' => 'Pediatrics', 'icon' => '🧒', 'description' => 'Medical care for infants, children and adolescents.'],
            ['name' => 'Dermatology', 'icon' => '🧴', 'description' => 'Skin, hair, nail and allergic skin conditions.'],
            ['name' => 'Neurology', 'icon' => '🧠', 'description' => 'Brain, spinal cord and nerve disorders.'],
            ['name' => 'Orthopaedics', 'icon' => '🦴', 'description' => 'Bones, joints, muscles and sports injuries.'],
            ['name' => 'Gastroenterology', 'icon' => '🫃', 'description' => 'Digestive tract, liver and nutritional disorders.'],
            ['name' => 'Endocrinology', 'icon' => '⚖️', 'description' => 'Diabetes, thyroid and hormonal conditions.'],
            ['name' => 'Otolaryngology (ENT)', 'icon' => '👂', 'description' => 'Ear, nose, throat and sinus conditions.'],
            ['name' => 'Psychiatry', 'icon' => '🧘', 'description' => 'Mental health assessment and treatment.'],
            ['name' => 'General Medicine', 'icon' => '🩺', 'description' => 'First-contact adult care and chronic disease follow-up.'],
            ['name' => 'Pulmonology', 'icon' => '🫁', 'description' => 'Asthma, COPD and other lung conditions.'],
            ['name' => 'Nephrology', 'icon' => '🫘', 'description' => 'Kidney function, dialysis and fluid balance.'],
            ['name' => 'Ophthalmology', 'icon' => '👁️', 'description' => 'Eye health, vision and eye surgery.'],
            ['name' => 'Obstetrics & Gynaecology', 'icon' => '🤰', 'description' => 'Pregnancy, reproductive and women’s health care.'],
        ];

        foreach ($specialties as $index => $specialty) {
            Specialty::query()->updateOrCreate(
                ['slug' => Str::slug($specialty['name'])],
                [
                    'name' => $specialty['name'],
                    'description' => $specialty['description'],
                    'icon' => $specialty['icon'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
