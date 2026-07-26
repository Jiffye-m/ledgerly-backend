<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'sale' => $this->whenLoaded('sale', fn () => [
                'id' => $this->sale->id,
                'invoice_number' => $this->sale->invoice_number,
            ]),
            'customer' => $this->relationLoaded('customer') ? new CustomerResource($this->customer) : null,
            'total_refund' => $this->total_refund,
            'reason' => $this->reason,
            'items' => ReturnItemResource::collection($this->whenLoaded('items')),
            'processed_by' => $this->whenLoaded('user', fn () => $this->user->name),
            'created_at' => $this->created_at,
        ];
    }
}
