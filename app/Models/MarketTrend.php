<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketTrend extends Model
{
    protected $fillable = [
        'gold_trend',
        'dollar_trend',
        'updated_by',
    ];
}