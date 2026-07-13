<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo' => $this->logo,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'setting' => new SettingResource($this->whenLoaded('setting')),
            'created_at' => $this->created_at,
        ];
    }
}
