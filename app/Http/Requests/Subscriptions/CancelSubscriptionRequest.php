<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_at_period_end' => ['nullable', 'boolean'],
        ];
    }
}
