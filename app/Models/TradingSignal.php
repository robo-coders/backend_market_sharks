<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingSignal extends Model
{
    protected $fillable = [
        'symbol',
        'signal_type',
        'gold_price_at_entry',
        'entry_price',
        'stop_loss',
        'take_profit',
        'status',
        'opened_at',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'gold_price_at_entry' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'take_profit' => 'decimal:2',
        'opened_at' => 'datetime',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tradeLogs(): HasMany
    {
        return $this->hasMany(TradeLog::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function hasReachedEntry(float $price): bool
    {
        $entry = (float) $this->entry_price;
        $placedPrice = (float) ($this->gold_price_at_entry ?? $entry);

        if ($entry <= $placedPrice) {
            return $price <= $entry;
        }

        return $price >= $entry;
    }
}