<?php

namespace App\Http\Requests\Tenant\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['nullable', 'date'],
            'receive_notes' => ['nullable', 'string'],
        ];
    }
}
