<?php

namespace App\Http\Requests\Plans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
