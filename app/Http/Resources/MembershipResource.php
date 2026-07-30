<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'business' => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'slug' => $this->business->slug,
                'currency_symbol' => $this->business->currency_symbol,
            ],
            'role' => $this->role,
            'branch' => $this->relationLoaded('branch') && $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'status' => $this->status,
        ];
    }
}
