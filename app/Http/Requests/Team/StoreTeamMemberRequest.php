<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Not unique:users,email anymore — a person can already have a
            // Ledgerly account from another business. If they do, the
            // controller attaches them instead of creating a duplicate.
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            // Only used if this email doesn't belong to an existing account yet
            // (the controller ignores it otherwise) — always required from the
            // form since there's no "set your own password later" flow yet.
            'password' => ['required', 'string', 'min:8'],
            // Deliberately excludes 'owner' — ownership isn't handed out
            // through this endpoint.
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $this->business()->id)),
            ],
        ];
    }
}
