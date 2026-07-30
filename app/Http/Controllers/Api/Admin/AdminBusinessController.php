<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateSubscriptionRequest;
use App\Http\Requests\Admin\ExtendTrialRequest;
use App\Http\Resources\Admin\AdminBusinessDetailResource;
use App\Http\Resources\Admin\AdminBusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminBusinessController extends Controller
{
    /**
     * GET /admin/businesses?search=&status=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Business::with(['subscription.plan', 'owner'])
            ->withCount(['members', 'products', 'sales']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->whereHas('subscription', fn ($q) => $q->where('status', $status));
        }

        $businesses = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'businesses' => AdminBusinessResource::collection($businesses),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'total' => $businesses->total(),
            ],
        ]);
    }

    public function show(Business $business): JsonResponse
    {
        $business->loadCount(['members', 'products', 'sales'])
            ->loadSum('sales', 'total') // adds sales_sum_total
            ->load(['subscription.plan', 'owner', 'payments' => fn ($q) => $q->latest()->with('recordedBy')]);

        // Resource expects `sales_total`; map the Eloquent-generated
        // `sales_sum_total` to that friendlier name.
        $business->setAttribute('sales_total', $business->sales_sum_total ?? 0);

        return response()->json([
            'business' => new AdminBusinessDetailResource($business),
        ]);
    }

    /**
     * POST /admin/businesses/{business}/activate — "they paid, mark it active."
     */
    public function activate(ActivateSubscriptionRequest $request, Business $business): JsonResponse
    {
        $subscription = $business->subscription;

        $subscription->update([
            'plan_id' => $request->plan_id,
            'status' => 'active',
            'expires_at' => Carbon::today()->addDays($request->duration_days),
            'payment_provider' => $request->payment_provider ?? 'manual',
        ]);

        return response()->json([
            'business' => new AdminBusinessDetailResource(
                $business->fresh(['subscription.plan', 'owner'])->loadCount(['members', 'products', 'sales'])
            ),
        ]);
    }

    /**
     * POST /admin/businesses/{business}/extend-trial
     */
    public function extendTrial(ExtendTrialRequest $request, Business $business): JsonResponse
    {
        $subscription = $business->subscription;
        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
            ? $subscription->trial_ends_at
            : Carbon::today();

        $subscription->update([
            'trial_ends_at' => $base->copy()->addDays($request->days),
            'status' => $subscription->status === 'suspended' ? 'trialing' : $subscription->status,
        ]);

        return response()->json([
            'business' => new AdminBusinessDetailResource(
                $business->fresh(['subscription.plan', 'owner'])->loadCount(['members', 'products', 'sales'])
            ),
        ]);
    }

    /**
     * POST /admin/businesses/{business}/suspend — locks the business out
     * immediately (abuse, chargebacks, whatever the reason).
     */
    public function suspend(Business $business): JsonResponse
    {
        $business->subscription->update(['status' => 'suspended']);

        return response()->json(['message' => 'Business suspended.']);
    }

    /**
     * POST /admin/businesses/{business}/reactivate — undoes a suspension,
     * restoring whichever state (trial or paid) makes sense from the
     * dates already on file.
     */
    public function reactivate(Business $business): JsonResponse
    {
        $subscription = $business->subscription;

        $stillPaid = $subscription->plan_id && $subscription->expires_at && $subscription->expires_at->isFuture();
        $subscription->update(['status' => $stillPaid ? 'active' : 'trialing']);

        return response()->json([
            'business' => new AdminBusinessDetailResource(
                $business->fresh(['subscription.plan', 'owner'])->loadCount(['members', 'products', 'sales'])
            ),
        ]);
    }
}
