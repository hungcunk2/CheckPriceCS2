<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\SubscriptionPlans;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_active',
        'paid_until',
        'subscription_plan',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'is_active' => 'boolean',
            'paid_until' => 'datetime',
        ];
    }

    public function hasActiveSubscription(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->paid_until === null) {
            return true;
        }

        return $this->paid_until->isFuture();
    }

    public function subscriptionPlanLabel(): ?string
    {
        if ($this->subscription_plan === null || $this->subscription_plan === '') {
            return null;
        }

        return SubscriptionPlans::get($this->subscription_plan)['name']
            ?? strtoupper($this->subscription_plan);
    }

    /** null = không giới hạn số kho. */
    public function inventorySlotLimit(): ?int
    {
        return SubscriptionPlans::inventoryLimit($this->subscription_plan);
    }

    public function supportConversation(): HasOne
    {
        return $this->hasOne(SupportConversation::class);
    }
}

