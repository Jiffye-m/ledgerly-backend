<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // Optional: also extends/activates the subscription in the same action
            'plan_id' => ['nullable', 'exists:plans,id'],
            'extend_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
