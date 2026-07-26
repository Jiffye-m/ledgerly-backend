<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'is_super_admin' => $this->is_super_admin,
            'email_verified_at' => $this->email_verified_at,
            'business' => $this->relationLoaded('business') ? new BusinessResource($this->business) : null,
            'created_at' => $this->created_at,
        ];
    }
}
