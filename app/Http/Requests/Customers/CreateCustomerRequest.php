<?php

namespace App\Http\Requests\Customers;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::id())
                ),
            ],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'billing_address' => ['nullable', 'array'],
        ];
    }
}
