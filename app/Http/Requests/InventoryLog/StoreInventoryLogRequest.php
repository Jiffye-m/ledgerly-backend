<?php

namespace App\Http\Requests\InventoryLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $this->business()->id)),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('business_id', $this->business()->id)),
            ],
            // sale/void_restock are only ever written by SaleController; formal
            // customer returns against a specific sale go through
            // ReturnController instead — this 'return' type is for stock that
            // came back without a tracked original sale (e.g. from a supplier)
            'type' => ['required', Rule::in(['purchase', 'return', 'adjustment'])],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
