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
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $this->user()->business_id)),
            ],
            // sale/void_restock are only ever written by SaleController — never accepted from a request
            'type' => ['required', Rule::in(['purchase', 'return', 'adjustment'])],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
