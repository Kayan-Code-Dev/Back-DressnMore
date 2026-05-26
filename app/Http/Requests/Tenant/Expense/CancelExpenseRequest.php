<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
