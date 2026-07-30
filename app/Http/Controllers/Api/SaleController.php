<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * GET /sales?customer_id=&status=&from=&to=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user', 'branch'])
            ->where('business_id', $request->business()->id);

        if ($branchId = $this->requiredBranchId() ?? $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sales = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'sales' => SaleResource::collection($sales),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * The core POS transaction: validates stock, decrements it, snapshots
     * price/name into sale_items, logs each stock movement, and updates
     * the customer's running total — all inside one DB transaction so a
     * mid-way failure never leaves any of that half-done.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $businessId = $request->business()->id;

        $sale = DB::transaction(function () use ($request, $businessId) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($request->items as $item) {
                // lockForUpdate: if two cashiers sell the last unit at the
                // same second, the second one waits here and then correctly
                // sees 0 stock, instead of both succeeding and going negative.
                $product = Product::where('business_id', $businessId)
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

                if ($product->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Not enough stock for \"{$product->name}\". Available: {$product->quantity}."],
                    ]);
                }

                $unitPrice = $item['unit_price'] ?? $product->selling_price;
                $lineSubtotal = $unitPrice * $item['quantity'];
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = $request->input('discount', 0);
            $tax = $request->input('tax', 0);
            $total = $subtotal - $discount + $tax;
            $amountPaid = $request->amount_paid;
            $change = max(0, $amountPaid - $total);

            $sale = Sale::create([
                'business_id' => $businessId,
                'branch_id' => $this->resolveBranchIdForWrite($request->branch_id),
                'user_id' => $request->user()->id,
                'customer_id' => $request->customer_id,
                'invoice_number' => $this->nextInvoiceNumber($businessId),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'amount_paid' => $amountPaid,
                'change' => $change,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            foreach ($lineItems as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['product']->cost_price,
                    'subtotal' => $line['subtotal'],
                ]);

                $line['product']->decrement('quantity', $line['quantity']);

                InventoryLog::create([
                    'business_id' => $businessId,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'type' => 'sale',
                    'quantity_change' => -$line['quantity'],
                    'quantity_after' => $line['product']->quantity,
                    'user_id' => $request->user()->id,
                    'sale_id' => $sale->id,
                ]);
            }

            if ($request->customer_id) {
                Customer::where('id', $request->customer_id)->increment('total_purchases', $total);
            }

            return $sale;
        });

        return response()->json([
            'sale' => new SaleResource($sale->load(['items', 'customer', 'user', 'branch'])),
        ], 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->authorizeBusiness($sale);

        return response()->json([
            'sale' => new SaleResource($sale->load(['items', 'customer', 'user', 'branch'])),
        ]);
    }

    /**
     * Void a sale: restocks every item, logs the restock, and reverses the
     * customer's total. Sales are never hard-deleted — voiding preserves
     * the audit trail (invoice number, who made it, when) which matters
     * for reconciliation.
     */
    public function void(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeBusiness($sale);

        if ($sale->status !== 'completed') {
            return response()->json(['message' => 'Only completed sales can be voided.'], 422);
        }

        DB::transaction(function () use ($sale, $request) {
            foreach ($sale->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }

                $product->increment('quantity', $item->quantity);

                InventoryLog::create([
                    'business_id' => $sale->business_id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'type' => 'void_restock',
                    'quantity_change' => $item->quantity,
                    'quantity_after' => $product->quantity,
                    'user_id' => $request->user()->id,
                    'sale_id' => $sale->id,
                ]);
            }

            if ($sale->customer_id) {
                Customer::where('id', $sale->customer_id)->decrement('total_purchases', $sale->total);
            }

            $sale->update(['status' => 'void']);
        });

        return response()->json([
            'sale' => new SaleResource($sale->fresh()->load(['items', 'customer', 'user', 'branch'])),
        ]);
    }

    private function nextInvoiceNumber(int $businessId): string
    {
        $count = Sale::where('business_id', $businessId)->lockForUpdate()->count();

        return 'INV-'.str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}
