<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'sale_id' => [
                'required',
                Rule::exists('sales', 'id')->where(fn ($q) => $q
                    ->where('business_id', $businessId)
                    ->where('status', 'completed')),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => [
                'required',
                Rule::exists('sale_items', 'id')->where(fn ($q) => $q->where('sale_id', $this->input('sale_id'))),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
