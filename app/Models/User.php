<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Platform-level access (you, running Ledgerly as a SaaS) — entirely
     * separate from `role`, which only ever means "owner/admin/staff
     * within one business." A super admin may not belong to any business
     * at all.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
