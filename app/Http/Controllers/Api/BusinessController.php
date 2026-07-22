<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    /**
     * A logged-in user with no business yet creates one here. This is the
     * screen right after signup in the onboarding flow.
     */
    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->business_id) {
            return response()->json([
                'message' => 'You already belong to a business.',
            ], 422);
        }

        $business = Business::create([
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

        $user->update([
            'business_id' => $business->id,
            'role' => 'owner',
        ]);

        return response()->json([
            'business' => new BusinessResource($business->load(['setting', 'subscription'])),
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business()->with(['setting', 'subscription'])->first();

        if (! $business) {
            return response()->json(['message' => 'No business found.'], 404);
        }

        return response()->json([
            'business' => new BusinessResource($business),
        ]);
    }

    public function update(UpdateBusinessRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->business_id) {
            return response()->json(['message' => 'No business found.'], 404);
        }

        if (! $user->isOwner()) {
            return response()->json(['message' => 'Only the business owner can update this.'], 403);
        }

        $business = $user->business;
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
        $user = $request->user();

        if (! $user->isOwner()) {
            return response()->json(['message' => 'Only the business owner can update this.'], 403);
        }

        $setting = $user->business->setting;
        $setting->update($request->validated());

        return response()->json([
            'business' => new BusinessResource($user->business->fresh(['setting', 'subscription'])),
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
