<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'receipt_footer',
        'whatsapp_enabled',
        'email_enabled',
        'tax_rate',
        'low_stock_alerts',
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'low_stock_alerts' => 'boolean',
        'tax_rate' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
