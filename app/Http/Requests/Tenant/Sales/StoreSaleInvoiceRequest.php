<?php

namespace App\Http\Requests\Tenant\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('tenant.customers', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'order_notes' => ['nullable', 'string'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dress_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['nullable', 'numeric', 'min:0'],
            'initial_payment.method' => ['nullable', 'string', 'max:50'],
        ];
    }
}
