<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\Customer;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Return_;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    /**
     * GET /returns?sale_id=&customer_id=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Return_::with(['sale', 'customer', 'user'])
            ->where('business_id', $request->business()->id);

        if ($saleId = $request->query('sale_id')) {
            $query->where('sale_id', $saleId);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $returns = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'returns' => ReturnResource::collection($returns),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'total' => $returns->total(),
            ],
        ]);
    }

    /**
     * POST /returns — a customer brings back some (not necessarily all)
     * items from a specific completed sale. Restocks each item, logs the
     * movement, and reduces the customer's running total — but never
     * edits the original sale itself, so the original invoice still
     * reflects exactly what was sold that day. The return is its own
     * record, the way a credit note works alongside an invoice.
     */
    public function store(StoreReturnRequest $request): JsonResponse
    {
        $businessId = $request->business()->id;

        $return = DB::transaction(function () use ($request, $businessId) {
            $sale = Sale::where('business_id', $businessId)->findOrFail($request->sale_id);

            $totalRefund = 0;
            $lines = [];

            foreach ($request->items as $item) {
                $saleItem = SaleItem::where('sale_id', $sale->id)
                    ->lockForUpdate()
                    ->findOrFail($item['sale_item_id']);

                $alreadyReturned = ReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                $remaining = $saleItem->quantity - $alreadyReturned;

                if ($item['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return {$item['quantity']} of \"{$saleItem->product_name}\" — only {$remaining} eligible (already returned: {$alreadyReturned})."],
                    ]);
                }

                $subtotal = $saleItem->unit_price * $item['quantity'];
                $totalRefund += $subtotal;

                $lines[] = [
                    'sale_item' => $saleItem,
                    'quantity' => $item['quantity'],
                    'unit_price' => $saleItem->unit_price,
                    'subtotal' => $subtotal,
                ];
            }

            $return = Return_::create([
                'business_id' => $businessId,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'return_number' => $this->nextReturnNumber($businessId),
                'total_refund' => $totalRefund,
                'reason' => $request->reason,
            ]);

            foreach ($lines as $line) {
                $saleItem = $line['sale_item'];

                ReturnItem::create([
                    'return_id' => $return->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_name' => $saleItem->product_name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);

                if ($saleItem->product_id) {
                    $product = Product::where('id', $saleItem->product_id)->lockForUpdate()->first();

                    if ($product) {
                        $product->increment('quantity', $line['quantity']);

                        InventoryLog::create([
                            'business_id' => $businessId,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'type' => 'return',
                            'quantity_change' => $line['quantity'],
                            'quantity_after' => $product->quantity,
                            'user_id' => $request->user()->id,
                            'sale_id' => $sale->id,
                            'return_id' => $return->id,
                        ]);
                    }
                }
            }

            if ($sale->customer_id) {
                Customer::where('id', $sale->customer_id)->decrement('total_purchases', $totalRefund);
            }

            return $return;
        });

        return response()->json([
            'return' => new ReturnResource($return->load(['sale', 'customer', 'user', 'items'])),
        ], 201);
    }

    public function show(Return_ $return): JsonResponse
    {
        $this->authorizeBusiness($return);

        return response()->json([
            'return' => new ReturnResource($return->load(['sale', 'customer', 'user', 'items'])),
        ]);
    }

    private function nextReturnNumber(int $businessId): string
    {
        $count = Return_::where('business_id', $businessId)->lockForUpdate()->count();

        return 'RET-'.str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}
