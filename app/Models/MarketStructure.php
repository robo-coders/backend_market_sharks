<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStructure extends Model
{
    protected $fillable = [
        'support_1',
        'support_2',
        'support_3',
        'resistance_1',
        'resistance_2',
        'resistance_3',
        'updated_by',
    ];

    protected $casts = [
        'support_1' => 'decimal:2',
        'support_2' => 'decimal:2',
        'support_3' => 'decimal:2',
        'resistance_1' => 'decimal:2',
        'resistance_2' => 'decimal:2',
        'resistance_3' => 'decimal:2',
    ];
}