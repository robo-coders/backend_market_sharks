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

    /**
     * A pending signal has been placed but live price has not yet
     * reached its entry. It is NOT a live trade: no P/L, no TP/SL
     * auto-close, and cancelling it leaves no trade log.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * An open signal is a live trade — price has reached entry.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * True once the live price has reached the entry, using standard
     * limit-order semantics:
     *
     *   - BUY  activates when price falls TO or below entry (price <= entry).
     *          You're buying a dip down to your level.
     *   - SELL activates when price rises TO or above entry (price >= entry).
     *          You're selling a rally up to your level.
     *
     * Direction-aware. The previous single `price >= entry` rule wrongly
     * activated buy signals as soon as price was above entry.
     */
    public function hasReachedEntry(float $price): bool
    {
        $entry = (float) $this->entry_price;

        return $this->signal_type === 'buy'
            ? $price <= $entry
            : $price >= $entry;
    }
}