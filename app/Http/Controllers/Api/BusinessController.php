<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    /**
     * Creates a business owned by the logged-in user — who may already
     * own others. There's no "only one business per account" check
     * anymore; the limit (if any) belongs on the plan, not hard-coded
     * here.
     */
    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $user = $request->user();

        $business = Business::create([
            'owner_user_id' => $user->id,
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name),
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'currency' => $request->currency ?? 'NGN',
            'currency_symbol' => $request->currency_symbol ?? '₦',
            'timezone' => $request->timezone ?? 'Africa/Lagos',
        ]);

        Setting::create(['business_id' => $business->id]);

        // Every new business starts on a 14-day trial, no plan chosen yet
        // — they pick/pay for a plan when they actually convert (handled
        // manually via the admin panel for now).
        Subscription::create([
            'business_id' => $business->id,
            'plan_id' => null,
            'status' => 'trialing',
            'starts_at' => Carbon::today(),
            'trial_ends_at' => Carbon::today()->addDays(14),
        ]);

        BusinessMember::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'branch_id' => null,
            'status' => 'active',
        ]);

        return response()->json([
            'business' => new BusinessResource($business->load(['setting', 'subscription'])),
        ], 201);
    }

    /**
     * The currently-selected business (resolved from the X-Business-Id
     * header by the has.business middleware) — not "my one business"
     * anymore, since there could be several.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'business' => new BusinessResource($request->business()->load(['setting', 'subscription'])),
        ]);
    }

    public function update(UpdateBusinessRequest $request): JsonResponse
    {
        $business = $request->business();
        $business->update($request->validated());

        return response()->json([
            'business' => new BusinessResource($business->load(['setting', 'subscription'])),
        ]);
    }

    /**
     * Receipt footer, tax rate, and notification toggles — separate from
     * updating the business's own name/address, since these are the
     * "how the business operates" knobs rather than identity fields.
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $business = $request->business();
        $business->setting->update($request->validated());

        return response()->json([
            'business' => new BusinessResource($business->fresh(['setting', 'subscription'])),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Business::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
