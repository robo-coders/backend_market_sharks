<?php

namespace Database\Seeders;

use App\Models\MarketTrend;
use Illuminate\Database\Seeder;

class MarketTrendSeeder extends Seeder
{
    public function run(): void
    {
        MarketTrend::updateOrCreate(
            ['id' => 1],
            [
                'gold_trend' => 'neutral',
                'dollar_trend' => 'neutral',
                'updated_by' => null,
            ]
        );
    }
}