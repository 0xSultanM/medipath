<?php

namespace Database\Seeders;

use App\Models\DrugInteraction;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class DrugInteractionSeeder extends Seeder
{
    public function run(): void
    {
        $interactions = [
            ['Warfarin', 'Aspirin', 'major', 'Additive bleeding risk: warfarin plus antiplatelet therapy increases major haemorrhage.', 'Avoid unless a clear indication exists; monitor INR and signs of bleeding.'],
            ['Warfarin', 'Ibuprofen', 'major', 'NSAIDs add antiplatelet effect and GI mucosal injury, sharply raising bleeding risk.', 'Prefer paracetamol; if unavoidable, add gastroprotection and monitor INR.'],
            ['Warfarin', 'Metronidazole', 'major', 'Metronidazole inhibits warfarin metabolism, increasing INR and bleeding risk.', 'Reduce warfarin dose, check INR within 3 days.'],
            ['Aspirin', 'Ibuprofen', 'moderate', 'Ibuprofen competes with aspirin at COX-1 and can blunt cardioprotection while increasing GI injury.', 'Separate doses or choose paracetamol.'],
            ['Atorvastatin', 'Azithromycin', 'moderate', 'Macrolides can raise statin exposure and myopathy risk.', 'Consider holding the statin during the antibiotic course; counsel on muscle pain.'],
            ['Ciprofloxacin', 'Ondansetron', 'moderate', 'Both prolong the QT interval.', 'Check electrolytes; avoid in patients with long QT.'],
            ['Salbutamol inhaler', 'Prednisolone', 'moderate', 'Beta-agonists and corticosteroids both lower serum potassium.', 'Monitor potassium in severe asthma exacerbations.'],
            ['Tramadol', 'Diazepam', 'contraindicated', 'Combined opioid and benzodiazepine use causes additive respiratory depression and sedation.', 'Do not co-prescribe; if unavoidable, use the lowest dose with close monitoring.'],
            ['Levothyroxine', 'Calcium carbonate', 'moderate', 'Calcium binds levothyroxine and reduces absorption.', 'Separate administration by at least 4 hours.'],
            ['Ramipril', 'Losartan', 'major', 'Dual RAAS blockade increases hyperkalaemia and acute kidney injury without added benefit.', 'Use one agent only.'],
            ['Clopidogrel', 'Omeprazole', 'moderate', 'Omeprazole inhibits CYP2C19 and can reduce clopidogrel activation.', 'Prefer pantoprazole if a PPI is required.'],
            ['Ferrous sulfate', 'Omeprazole', 'minor', 'Reduced gastric acid decreases iron absorption.', 'Separate doses; consider vitamin C with iron.'],
            ['Metformin', 'Furosemide', 'minor', 'Diuretic-induced dehydration can impair renal function and raise metformin accumulation risk.', 'Monitor hydration and renal function.'],
            ['Prednisolone', 'Ibuprofen', 'moderate', 'Combined corticosteroid and NSAID use increases peptic ulcer risk.', 'Add gastroprotection or avoid the combination.'],
            ['Ciprofloxacin', 'Tramadol', 'moderate', 'Both agents lower the seizure threshold; combined use increases seizure risk.', 'Avoid in patients with epilepsy; use an alternative analgesic.'],
        ];

        foreach ($interactions as [$nameA, $nameB, $severity, $description, $management]) {
            $a = Medicine::query()->where('name', $nameA)->first();
            $b = Medicine::query()->where('name', $nameB)->first();

            if (! $a || ! $b) {
                continue;
            }

            [$first, $second] = DrugInteraction::canonicalPair($a->id, $b->id);

            DrugInteraction::query()->updateOrCreate(
                ['medicine_a_id' => $first, 'medicine_b_id' => $second],
                [
                    'severity' => $severity,
                    'description' => $description,
                    'management' => $management,
                ],
            );
        }
    }
}
