<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActivateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:plans,id'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'payment_provider' => ['nullable', 'string', 'max:50'],
        ];
    }
}
