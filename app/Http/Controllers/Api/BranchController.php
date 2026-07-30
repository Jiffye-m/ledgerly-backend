<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Every team member can see the branch list (needed for e.g. a branch
     * picker at login) — only owner/admin can create/edit/deactivate one.
     */
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::withCount('members')
            ->where('business_id', $request->business()->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'branches' => BranchResource::collection($branches),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create([
            ...$request->validated(),
            'business_id' => $request->business()->id,
        ]);

        return response()->json([
            'branch' => new BranchResource($branch),
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorizeBusiness($branch);

        return response()->json([
            'branch' => new BranchResource($branch->loadCount('members')),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorizeBusiness($branch);

        $branch->update($request->validated());

        return response()->json([
            'branch' => new BranchResource($branch),
        ]);
    }

    /**
     * No destroy() — a branch with historical sales/expenses shouldn't
     * vanish (same cascade-delete danger as everywhere else in this app).
     * Deactivating (is_active=false via update()) is the supported way
     * to retire one.
     */
}
