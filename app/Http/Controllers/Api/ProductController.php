<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /products?search=&category_id=&low_stock=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'supplier'])
            ->where('business_id', $request->user()->business_id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $products = $query->orderBy('name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'products' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * GET /products/barcode/{barcode} — exact match, used by the POS
     * barcode scanner. Registered before the apiResource show route so it
     * takes priority over the numeric {product} pattern.
     */
    public function findByBarcode(Request $request, string $barcode): JsonResponse
    {
        $product = Product::with(['category', 'supplier'])
            ->where('business_id', $request->user()->business_id)
            ->where('barcode', $barcode)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'No product found with that barcode.'], 404);
        }

        return response()->json(['product' => new ProductResource($product)]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        if ($product->quantity > 0) {
            InventoryLog::create([
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => 'adjustment',
                'quantity_change' => $product->quantity,
                'quantity_after' => $product->quantity,
                'user_id' => $request->user()->id,
                'note' => 'Initial stock on product creation',
            ]);
        }

        return response()->json([
            'product' => new ProductResource($product->load(['category', 'supplier'])),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorizeBusiness($product);

        return response()->json([
            'product' => new ProductResource($product->load(['category', 'supplier'])),
        ]);
    }

    /**
     * If the quantity field changes here (a direct edit, not a sale), it's
     * logged as an 'adjustment' so the inventory history stays complete —
     * otherwise stock could silently jump with no record of why.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorizeBusiness($product);

        $before = $product->quantity;
        $product->update($request->validated());

        if ($product->wasChanged('quantity') && $product->quantity !== $before) {
            InventoryLog::create([
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => 'adjustment',
                'quantity_change' => $product->quantity - $before,
                'quantity_after' => $product->quantity,
                'user_id' => $request->user()->id,
                'note' => 'Manual edit via product form',
            ]);
        }

        return response()->json([
            'product' => new ProductResource($product->load(['category', 'supplier'])),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorizeBusiness($product);

        // sale_items keep a name/price snapshot, so past receipts stay
        // intact even after the product itself is deleted.
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
