<?php

namespace App\Http\Requests\Tenant\Invoice;

use App\Models\Tenant\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('tenant.customers', 'id')->whereNull('deleted_at')],
            'type' => ['required', 'string', Rule::in(Invoice::types())],
            'status' => ['nullable', 'string', Rule::in(Invoice::statuses())],

            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],

            'rent_start_date' => ['nullable', 'date', 'required_if:type,rent'],
            'rent_end_date' => ['nullable', 'date', 'required_if:type,rent', 'after_or_equal:rent_start_date'],
            'delivery_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'security_deposit_status' => ['nullable', 'string', 'max:100'],

            'tailoring_due_date' => ['nullable', 'date'],
            'tailoring_notes' => ['nullable', 'string'],

            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.dress_id' => ['nullable', 'integer', Rule::exists('tenant.dresses', 'id')->whereNull('deleted_at')],
            'items.*.item_type' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['required_with:initial_payment', 'numeric', 'gt:0'],
            'initial_payment.method' => ['nullable', 'string', 'max:100'],
            'initial_payment.reference' => ['nullable', 'string', 'max:255'],
            'initial_payment.paid_at' => ['nullable', 'date'],
            'initial_payment.notes' => ['nullable', 'string'],
        ];
    }
}
