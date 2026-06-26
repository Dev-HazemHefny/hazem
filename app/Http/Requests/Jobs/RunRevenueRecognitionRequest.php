<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Foundation\Http\FormRequest;

class RunRevenueRecognitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_end' => ['nullable', 'date'],
        ];
    }
}
