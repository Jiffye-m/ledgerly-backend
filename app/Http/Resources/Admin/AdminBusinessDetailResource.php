<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBusinessDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->users->firstWhere('role', 'owner');
        $subscription = $this->subscription;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email, 'phone' => $owner->phone] : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan?->name,
                'starts_at' => $subscription->starts_at?->toDateString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'expires_at' => $subscription->expires_at?->toDateString(),
                'payment_provider' => $subscription->payment_provider,
                'days_left_in_trial' => $subscription->daysLeftInTrial(),
                'is_usable' => $subscription->isUsable(),
            ] : null,
            'usage' => [
                'team_members' => $this->users_count,
                'products' => $this->products_count,
                'total_sales' => $this->sales_count,
                'lifetime_revenue' => $this->sales_total,
            ],
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'provider' => $p->provider,
                'provider_reference' => $p->provider_reference,
                'status' => $p->status,
                'paid_at' => $p->paid_at,
                'notes' => $p->notes,
                'recorded_by' => $p->recordedBy?->name,
            ])),
        ];
    }
}
