<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'days_left_in_trial' => $this->daysLeftInTrial(),
            'is_usable' => $this->isUsable(),
        ];
    }
}
