<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Models\SymptomSpecialtyMap;
use Illuminate\Database\Seeder;

class SymptomMappingSeeder extends Seeder
{
    public function run(): void
    {
        $maps = [
            ['Chest pain & palpitations', ['chest pain', 'palpitations', 'heart racing', 'high blood pressure'], 'Cardiology', 10],
            ['Child fever & growth concerns', ['child fever', 'infant feeding', 'vaccination', 'growth delay'], 'Pediatrics', 10],
            ['Skin rash & acne', ['rash', 'acne', 'eczema', 'itching', 'hair loss'], 'Dermatology', 10],
            ['Headache, dizziness & numbness', ['headache', 'migraine', 'dizziness', 'numbness', 'seizure'], 'Neurology', 10],
            ['Joint, back & sports injury', ['joint pain', 'back pain', 'knee pain', 'fracture', 'sprain'], 'Orthopaedics', 10],
            ['Stomach pain & digestion', ['abdominal pain', 'heartburn', 'diarrhoea', 'constipation', 'bloating'], 'Gastroenterology', 10],
            ['Fatigue, thirst & weight change', ['fatigue', 'excess thirst', 'weight loss', 'weight gain', 'thyroid'], 'Endocrinology', 10],
            ['Ear, nose & throat', ['ear pain', 'sore throat', 'sinus', 'hearing loss', 'tonsils'], 'Otolaryngology (ENT)', 10],
            ['Anxiety, mood & sleep', ['anxiety', 'depression', 'insomnia', 'panic', 'stress'], 'Psychiatry', 10],
            ['Fever & general illness', ['fever', 'cough', 'cold', 'body ache', 'weakness'], 'General Medicine', 5],
            ['Chronic cough & wheezing', ['chronic cough', 'wheezing', 'asthma', 'breathlessness on exertion'], 'Pulmonology', 10],
            ['Swelling & urine changes', ['leg swelling', 'blood in urine', 'reduced urine', 'kidney pain'], 'Nephrology', 10],
            ['Eye pain & blurred vision', ['eye pain', 'blurred vision', 'red eye', 'floaters'], 'Ophthalmology', 10],
            ['Menstrual & pregnancy care', ['menstrual pain', 'irregular periods', 'pregnancy', 'prenatal'], 'Obstetrics & Gynaecology', 10],
        ];

        foreach ($maps as [$category, $keywords, $specialtyName, $priority]) {
            $specialty = Specialty::query()->where('name', $specialtyName)->first();

            if (! $specialty) {
                continue;
            }

            SymptomSpecialtyMap::query()->updateOrCreate(
                ['symptom_category' => $category, 'specialty_id' => $specialty->id],
                [
                    'keywords' => $keywords,
                    'priority' => $priority,
                    'is_active' => true,
                ],
            );
        }
    }
}
