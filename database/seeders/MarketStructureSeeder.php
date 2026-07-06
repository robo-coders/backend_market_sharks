<?php

namespace Database\Seeders;

use App\Models\MarketStructure;
use Illuminate\Database\Seeder;

class MarketStructureSeeder extends Seeder
{
    public function run(): void
    {
        MarketStructure::updateOrCreate(
            ['id' => 1],
            [
                'support_1' => 0,
                'support_2' => 0,
                'support_3' => 0,
                'resistance_1' => 0,
                'resistance_2' => 0,
                'resistance_3' => 0,
                'updated_by' => null,
            ]
        );
    }
}