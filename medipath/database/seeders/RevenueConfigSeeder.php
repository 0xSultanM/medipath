<?php

namespace Database\Seeders;

use App\Models\RevenueConfig;
use Illuminate\Database\Seeder;

class RevenueConfigSeeder extends Seeder
{
    public function run(): void
    {
        RevenueConfig::query()->updateOrCreate(
            ['effective_from' => now()->startOfYear()->toDateString()],
            [
                'percentage' => 8.00,
                'note' => 'Initial clinic commission configured by the administrator.',
            ],
        );
    }
}
