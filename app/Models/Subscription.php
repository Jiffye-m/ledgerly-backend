<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'expires_at',
        'payment_provider',
        'provider_subscription_id',
        'next_billing_date',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'trial_ends_at' => 'date',
        'expires_at' => 'date',
        'next_billing_date' => 'date',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The one method that actually gates access — checked on every
     * request via EnsureSubscriptionActive. Dates are the source of
     * truth, not just the status string, so a trial correctly locks out
     * the moment it's past trial_ends_at even if nothing has "run" to
     * flip the status to 'expired' yet.
     */
    public function isUsable(): bool
    {
        if (in_array($this->status, ['suspended', 'cancelled', 'expired'])) {
            return false;
        }

        if ($this->status === 'trialing') {
            return ! $this->trial_ends_at || $this->trial_ends_at->isFuture() || $this->trial_ends_at->isToday();
        }

        if ($this->status === 'active') {
            return ! $this->expires_at || $this->expires_at->isFuture() || $this->expires_at->isToday();
        }

        return false;
    }

    public function daysLeftInTrial(): ?int
    {
        if ($this->status !== 'trialing' || ! $this->trial_ends_at) {
            return null;
        }

        return max(0, Carbon::today()->diffInDays($this->trial_ends_at, false));
    }
}
