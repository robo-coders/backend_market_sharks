<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeLog extends Model
{
    protected $fillable = [
        'trading_signal_id',
        'symbol',
        'signal_type',
        'gold_price_at_entry',
        'gold_price_at_close',
        'entry_price',
        'close_price',
        'stop_loss',
        'take_profit',
        'profit_loss',
        'close_reason',
        'closed_at',
    ];

    protected $casts = [
        'gold_price_at_entry' => 'decimal:2',
        'gold_price_at_close' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'close_price' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'take_profit' => 'decimal:2',
        'profit_loss' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function tradingSignal(): BelongsTo
    {
        return $this->belongsTo(TradingSignal::class);
    }
}