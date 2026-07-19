<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Return_ extends Model
{
    use HasFactory;

    // Named Return_ because "Return" collides with the PHP reserved word;
    // the actual table is still `returns` (set explicitly below).
    protected $table = 'returns';

    protected $fillable = [
        'business_id',
        'sale_id',
        'customer_id',
        'user_id',
        'return_number',
        'total_refund',
        'reason',
    ];

    protected $casts = [
        'total_refund' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
