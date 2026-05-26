<?php

namespace App\Http\Requests\Tenant\Expense;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['nullable', 'integer', Rule::exists('tenant.expense_categories', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'cashbox_id' => ['nullable', 'integer', Rule::exists('tenant.cashboxes', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'status' => ['nullable', 'string', Rule::in(Expense::statuses())],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'vendor' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
