<?php

namespace App\Http\Requests\Tenant\ProductTransfer;

use Illuminate\Foundation\Http\FormRequest;

class RejectProductTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string'],
        ];
    }
}
