<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DraftSale\StoreDraftSaleRequest;
use App\Http\Resources\DraftSaleResource;
use App\Models\DraftSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftSaleController extends Controller
{
    /**
     * GET /draft-sales — any cashier can see and resume any draft on a
     * shift change, not just the one who saved it.
     */
    public function index(Request $request): JsonResponse
    {
        $drafts = DraftSale::with(['customer', 'user'])
            ->where('business_id', $request->business()->id)
            ->latest()
            ->get();

        return response()->json([
            'drafts' => DraftSaleResource::collection($drafts),
        ]);
    }

    public function store(StoreDraftSaleRequest $request): JsonResponse
    {
        $draft = DraftSale::create([
            'business_id' => $request->business()->id,
            'user_id' => $request->user()->id,
            'customer_id' => $request->customer_id,
            'items' => $request->items,
            'discount' => $request->input('discount', 0),
            'tax' => $request->input('tax', 0),
            'payment_method' => $request->payment_method,
            'note' => $request->note,
        ]);

        return response()->json([
            'draft' => new DraftSaleResource($draft->load(['customer', 'user'])),
        ], 201);
    }

    public function show(DraftSale $draftSale): JsonResponse
    {
        $this->authorizeBusiness($draftSale);

        return response()->json([
            'draft' => new DraftSaleResource($draftSale->load(['customer', 'user'])),
        ]);
    }

    /**
     * Update a paused cart in place (e.g. the customer added one more item
     * while they went to find their money).
     */
    public function update(StoreDraftSaleRequest $request, DraftSale $draftSale): JsonResponse
    {
        $this->authorizeBusiness($draftSale);

        $draftSale->update([
            'customer_id' => $request->customer_id,
            'items' => $request->items,
            'discount' => $request->input('discount', 0),
            'tax' => $request->input('tax', 0),
            'payment_method' => $request->payment_method,
            'note' => $request->note,
        ]);

        return response()->json([
            'draft' => new DraftSaleResource($draftSale->load(['customer', 'user'])),
        ]);
    }

    /**
     * Discards a draft — either the customer never came back, or it was
     * just completed via POST /sales and the frontend is cleaning up.
     */
    public function destroy(DraftSale $draftSale): JsonResponse
    {
        $this->authorizeBusiness($draftSale);
        $draftSale->delete();

        return response()->json(['message' => 'Draft removed.']);
    }
}
