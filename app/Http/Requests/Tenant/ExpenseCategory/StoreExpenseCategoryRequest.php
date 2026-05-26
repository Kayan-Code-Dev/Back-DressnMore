<?php

namespace App\Http\Requests\Tenant\ExpenseCategory;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('tenant.expense_categories', 'slug')->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(ExpenseStatus::values())],
        ];
    }
}
