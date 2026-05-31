<?php

namespace App\Http\Requests\Tenant;

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
            'workspace' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
