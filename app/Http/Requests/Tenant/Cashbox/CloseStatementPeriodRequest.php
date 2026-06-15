<?php

namespace App\Http\Requests\Tenant\Cashbox;

use Illuminate\Foundation\Http\FormRequest;

class CloseStatementPeriodRequest extends FormRequest
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
            'closing_date' => ['nullable', 'date'],
            'branch_id' => ['nullable'],
            'actual_balance' => ['required', 'numeric'],
        ];
    }
}
