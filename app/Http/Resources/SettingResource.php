<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'receipt_footer' => $this->receipt_footer,
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'email_enabled' => $this->email_enabled,
            'tax_rate' => $this->tax_rate,
            'low_stock_alerts' => $this->low_stock_alerts,
        ];
    }
}
