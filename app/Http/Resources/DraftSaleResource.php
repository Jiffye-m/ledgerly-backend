<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->relationLoaded('customer') ? new CustomerResource($this->customer) : null,
            'items' => $this->items,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'saved_by' => $this->whenLoaded('user', fn () => $this->user->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
