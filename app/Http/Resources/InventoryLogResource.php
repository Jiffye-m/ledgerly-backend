<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'type' => $this->type,
            'quantity_change' => $this->quantity_change,
            'quantity_after' => $this->quantity_after,
            'note' => $this->note,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'sale_invoice_number' => $this->whenLoaded('sale', fn () => $this->sale?->invoice_number),
            'created_at' => $this->created_at,
        ];
    }
}
