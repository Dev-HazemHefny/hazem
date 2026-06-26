<?php

namespace App\Http\Requests\Payments;

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
            'amount_cents' => ['required', 'integer', 'min:1'],
            'client_idempotency_key' => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
    }
}
