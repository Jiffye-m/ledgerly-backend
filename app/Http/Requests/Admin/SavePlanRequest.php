<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimes = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'name' => [$sometimes, 'required', 'string', 'max:255'],
            'price' => [$sometimes, 'required', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_products' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
