<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'is_super_admin' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Businesses this user created and owns outright — distinct from
     * `businesses()` below, which includes every business they're merely
     * a member of (as an invited admin or staff, say).
     */
    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_user_id');
    }

    /**
     * Every business this user belongs to in any role, via their
     * business_members rows — this is the "which businesses can I see"
     * list for the business switcher.
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')
            ->withPivot(['role', 'branch_id', 'status'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    /**
     * Platform-level access (you, running Ledgerly as a SaaS) — entirely
     * separate from business membership roles. A super admin may not
     * belong to any business at all.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
