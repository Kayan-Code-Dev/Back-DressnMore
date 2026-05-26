<?php

namespace App\Http\Requests\Tenant\Expense;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cashbox_id' => ['nullable', 'integer', Rule::exists('tenant.cashboxes', 'id')->whereNull('deleted_at')],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'paid_at' => ['nullable', 'date'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
