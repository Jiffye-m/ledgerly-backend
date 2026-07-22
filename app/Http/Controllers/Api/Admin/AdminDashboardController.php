<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $subscriptions = Subscription::with('plan')->get();

        // Classified by isUsable(), not just the raw status column — a
        // trial that's past trial_ends_at counts as expired here even if
        // nothing has run yet to flip its status field. Keeps this
        // dashboard honest without needing a scheduled job.
        $counts = ['trialing' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0, 'expired' => 0];
        $mrr = 0;

        foreach ($subscriptions as $sub) {
            if (in_array($sub->status, ['suspended', 'cancelled'])) {
                $counts[$sub->status]++;
            } elseif (! $sub->isUsable()) {
                $counts['expired']++;
            } else {
                $counts[$sub->status]++;
                if ($sub->status === 'active' && $sub->plan) {
                    $mrr += (float) $sub->plan->price;
                }
            }
        }

        $recentBusinesses = Business::with(['subscription.plan', 'users'])
            ->latest()->take(5)->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'owner_email' => $b->users->firstWhere('role', 'owner')?->email,
                'status' => $b->subscription?->status,
                'created_at' => $b->created_at,
            ]);

        $recentPayments = Payment::with('business')->latest()->take(5)->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'business_name' => $p->business->name,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at,
            ]);

        return response()->json([
            'total_businesses' => Business::count(),
            'subscription_counts' => $counts,
            'mrr' => $mrr,
            'recent_businesses' => $recentBusinesses,
            'recent_payments' => $recentPayments,
        ]);
    }
}
