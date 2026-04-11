<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'plan', 'status', 'starts_at', 'expires_at'];

    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];

    protected $touches = ['user'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->status === 'active'
            && $this->expires_at >= now()
            && $this->expires_at <= now()->addDays($days);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }
}