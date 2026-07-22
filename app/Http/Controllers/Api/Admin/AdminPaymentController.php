<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminPaymentController extends Controller
{
    /**
     * GET /admin/payments?business_id=&per_page= — across every business,
     * for a simple revenue/payment-history view.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['business', 'recordedBy']);

        if ($businessId = $request->query('business_id')) {
            $query->where('business_id', $businessId);
        }

        $payments = $query->latest()->paginate($request->integer('per_page', 30));

        return response()->json([
            'payments' => $payments->getCollection()->map(fn ($p) => [
                'id' => $p->id,
                'business' => ['id' => $p->business->id, 'name' => $p->business->name],
                'amount' => $p->amount,
                'currency' => $p->currency,
                'provider' => $p->provider,
                'provider_reference' => $p->provider_reference,
                'status' => $p->status,
                'paid_at' => $p->paid_at,
                'recorded_by' => $p->recordedBy?->name,
                'notes' => $p->notes,
            ])->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * POST /admin/businesses/{business}/payments — "I received the bank
     * transfer / Paystack link payment, log it." Optionally activates or
     * extends the subscription in the same step via plan_id/extend_days.
     */
    public function store(RecordPaymentRequest $request, Business $business): JsonResponse
    {
        $payment = Payment::create([
            'business_id' => $business->id,
            'subscription_id' => $business->subscription?->id,
            'amount' => $request->amount,
            'currency' => $business->currency,
            'provider' => 'manual',
            'provider_reference' => $request->provider_reference,
            'status' => 'successful',
            'paid_at' => $request->paid_at ? Carbon::parse($request->paid_at) : now(),
            'recorded_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        if ($request->extend_days && $business->subscription) {
            $subscription = $business->subscription;
            $base = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at
                : Carbon::today();

            $subscription->update([
                'plan_id' => $request->plan_id ?? $subscription->plan_id,
                'status' => 'active',
                'expires_at' => $base->copy()->addDays($request->extend_days),
                'payment_provider' => 'manual',
            ]);
        }

        return response()->json(['payment' => $payment->fresh(['recordedBy'])], 201);
    }
}
