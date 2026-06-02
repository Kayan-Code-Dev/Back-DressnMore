<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('workspace') && $this->headers->has('X-Tenant')) {
            $this->merge([
                'workspace' => $this->header('X-Tenant'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'workspace' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
