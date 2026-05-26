<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class ApproveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
