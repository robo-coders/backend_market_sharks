<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Subscription;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'whatsapp_number',
        'is_anonymous',
        'nickname',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['subscription', 'paymentRequest'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_anonymous' => 'boolean',
        ];
    }

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute(): string
    {
        return $this->is_anonymous
            ? ($this->nickname ?: 'Anonymous')
            : $this->name;
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function paymentRequest()
    {
        return $this->hasOne(\App\Models\PaymentRequest::class)->latest();
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Notification::class, 'notification_user', 'user_id', 'notification_id')
            ->withPivot('read_at')
            ->withTimestamps()
            ->latest('notifications.created_at');
    }
}