<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SpecialtySeeder::class,
            SystemSettingSeeder::class,
            RevenueConfigSeeder::class,
            MedicineSeeder::class,
            DrugInteractionSeeder::class,
            EmergencySeeder::class,
            SymptomMappingSeeder::class,
            FaqSeeder::class,
            DemoUsersSeeder::class,
            DemoClinicalSeeder::class,
        ]);
    }
}
