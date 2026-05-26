<?php

namespace App\Http\Requests\Tenant\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class ReturnPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'return_notes' => ['nullable', 'string'],
        ];
    }
}
