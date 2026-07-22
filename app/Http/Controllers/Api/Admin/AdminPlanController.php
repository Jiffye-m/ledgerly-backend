<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['plans' => Plan::orderBy('price')->get()]);
    }

    public function store(SavePlanRequest $request): JsonResponse
    {
        $plan = Plan::create($request->validated());

        return response()->json(['plan' => $plan], 201);
    }

    public function update(SavePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json(['plan' => $plan]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'This plan has businesses subscribed to it — deactivate it instead of deleting.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }
}
