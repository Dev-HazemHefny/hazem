<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('subscription_plans', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'effective_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }
}
