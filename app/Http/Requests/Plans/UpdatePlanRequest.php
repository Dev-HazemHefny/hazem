<?php

namespace App\Http\Requests\Plans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_interval' => ['sometimes', Rule::in(['monthly', 'yearly'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
