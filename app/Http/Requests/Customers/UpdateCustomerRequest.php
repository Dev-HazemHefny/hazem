<?php

namespace App\Http\Requests\Customers;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::id()))
                    ->ignore($this->route('customer')),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'billing_address' => ['nullable', 'array'],
        ];
    }
}
