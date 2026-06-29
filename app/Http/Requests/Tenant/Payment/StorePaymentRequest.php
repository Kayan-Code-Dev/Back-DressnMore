<?php

namespace App\Http\Requests\Tenant\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['invoice', 'general'])],
            'invoice_id' => ['required_if:kind,invoice', 'nullable', 'integer', Rule::exists('tenant.invoices', 'id')],
            'branch_id' => ['required', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'cashbox_id' => ['required', 'integer', Rule::exists('tenant.cashboxes', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
