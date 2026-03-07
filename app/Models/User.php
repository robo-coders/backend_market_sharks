<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Subscription;
use Spatie\Permission\Traits\HasRoles;



class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'whatsapp_number',
        'is_anonymous',
        'nickname',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_anonymous' => 'boolean',
        ];
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    protected $appends = ['display_name' , 'subscription_status'];

    public function getDisplayNameAttribute(): string
    {
        return $this->is_anonymous
            ? ($this->nickname ?: 'Anonymous')
            : $this->name;
    }

    public function getSubscriptionStatusAttribute(): string
    {
        if ($this->status === 'pending') return 'pending';
        if ($this->status === 'blocked') return 'blocked';


        if (!$this->subscription) return 'none';

        $expires_at = $this->subscription->expires_at;

        if ($expires_at->isPast()) return 'expired';
        if (now()->diffInDays($expires_at, false) <= 7) return 'expiring';

        return 'active';
    }

    public function paymentRequest()
    {
        return $this->hasOne(\App\Models\PaymentRequest::class)->latestOfMany();
    }


}
