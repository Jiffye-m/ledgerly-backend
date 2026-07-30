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
            'is_active' => $this->is_active,
            'is_super_admin' => $this->is_super_admin,
            'email_verified_at' => $this->email_verified_at,
            // No more singular 'business' or 'role' here — a user can
            // belong to several businesses in different roles now. See
            // GET /my/businesses for that list, and GET /business for
            // whichever one is currently selected (X-Business-Id).
            'created_at' => $this->created_at,
        ];
    }
}
