<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::withCount('products')
            ->where('business_id', $request->user()->business_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'suppliers' => SupplierResource::collection($suppliers),
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return response()->json([
            'supplier' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorizeBusiness($supplier);

        return response()->json([
            'supplier' => new SupplierResource($supplier->loadCount('products')),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorizeBusiness($supplier);

        $supplier->update($request->validated());

        return response()->json([
            'supplier' => new SupplierResource($supplier),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorizeBusiness($supplier);

        // Products keep existing (supplier_id -> null) via nullOnDelete on the migration
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }
}
