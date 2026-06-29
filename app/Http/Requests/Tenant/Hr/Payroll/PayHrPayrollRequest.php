<?php

namespace App\Http\Requests\Tenant\Hr\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayHrPayrollRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', Rule::exists('tenant.hr_employees', 'id')],
            'month' => ['required', 'date_format:Y-m'],
            'cashbox_id' => ['nullable', 'integer', Rule::exists('tenant.cashboxes', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
