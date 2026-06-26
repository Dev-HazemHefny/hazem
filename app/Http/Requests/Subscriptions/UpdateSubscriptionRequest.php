<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_date' => ['nullable', 'date'],
            'auto_renew' => ['nullable', 'boolean'],
        ];
    }
}
