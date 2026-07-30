<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Runs after EnsureBusinessExists, so a business is guaranteed to
     * exist here. If somehow no subscription row exists (shouldn't
     * happen — one is created alongside every business), fail safe by
     * blocking rather than silently allowing unpaid access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = $request->business()?->subscription;

        if (! $subscription || ! $subscription->isUsable()) {
            return response()->json([
                'message' => 'This business\'s trial or subscription is no longer active. Contact support to continue.',
                'code' => 'SUBSCRIPTION_INACTIVE',
                'status' => $subscription?->status,
            ], 402);
        }

        return $next($request);
    }
}
