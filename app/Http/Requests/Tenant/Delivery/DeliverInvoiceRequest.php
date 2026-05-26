<?php

namespace App\Http\Requests\Tenant\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class DeliverInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivered_at' => ['nullable', 'date'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
