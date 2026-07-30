<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->owner;
        $subscription = $this->subscription;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email] : null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan?->name,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'days_left_in_trial' => $subscription->daysLeftInTrial(),
                'is_usable' => $subscription->isUsable(),
            ] : null,
            'counts' => [
                'members' => $this->members_count,
                'products' => $this->products_count,
                'sales' => $this->sales_count,
            ],
        ];
    }
}
