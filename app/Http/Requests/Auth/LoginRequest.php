<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_slug' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
