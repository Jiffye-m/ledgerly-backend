<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryLog\StoreInventoryLogRequest;
use App\Http\Resources\InventoryLogResource;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryLogController extends Controller
{
    /**
     * GET /inventory-logs?product_id=&type=&per_page=
     * Every stock change, newest first — answers "where did my stock go?"
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryLog::with(['user', 'sale', 'supplier'])
            ->where('business_id', $request->user()->business_id);

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $logs = $query->latest()->paginate($request->integer('per_page', 30));

        return response()->json([
            'logs' => InventoryLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * POST /inventory-logs — manual stock movement: a delivery came in
     * (purchase), a customer brought something back outside of a formal
     * sale void (return), or a stock count didn't match reality
     * (adjustment, can be negative for damage/loss). Locks the product row
     * so this can never race with a simultaneous sale.
     */
    public function store(StoreInventoryLogRequest $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $log = DB::transaction(function () use ($request, $businessId) {
            $product = Product::where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($request->product_id);

            $newQuantity = $product->quantity + $request->quantity_change;

            if ($newQuantity < 0) {
                abort(422, "This would take stock below zero. Current stock: {$product->quantity}.");
            }

            $product->update(['quantity' => $newQuantity]);

            return InventoryLog::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'supplier_id' => $request->supplier_id,
                'product_name' => $product->name,
                'type' => $request->type,
                'quantity_change' => $request->quantity_change,
                'quantity_after' => $newQuantity,
                'user_id' => $request->user()->id,
                'note' => $request->note,
            ]);
        });

        return response()->json([
            'log' => new InventoryLogResource($log->load(['user', 'supplier'])),
        ], 201);
    }
}
