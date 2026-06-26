<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'plan_id' => ['required', 'uuid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_renew' => ['nullable', 'boolean'],
        ];
    }
}
