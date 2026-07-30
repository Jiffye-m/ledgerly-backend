<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'user_id',
        'customer_id',
        'items',
        'discount',
        'tax',
        'payment_method',
        'note',
    ];

    protected $casts = [
        'items' => 'array',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
