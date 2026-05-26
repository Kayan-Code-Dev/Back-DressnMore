<?php

namespace App\Http\Requests\Tenant\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class AddSecurityDepositDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
