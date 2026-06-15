<?php

namespace App\Http\Requests\Tenant\Hr\Payroll;

use App\Models\Tenant\HrPayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrPayrollAdjustmentRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in([
                HrPayrollAdjustment::TYPE_ADVANCE,
                HrPayrollAdjustment::TYPE_DEDUCTION,
                HrPayrollAdjustment::TYPE_BONUS,
                HrPayrollAdjustment::TYPE_COMMISSION,
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'invoice_id' => ['nullable', 'integer'],
        ];
    }
}
