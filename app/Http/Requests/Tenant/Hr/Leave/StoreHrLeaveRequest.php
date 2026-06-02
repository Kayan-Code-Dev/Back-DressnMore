<?php

namespace App\Http\Requests\Tenant\Hr\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('tenant.hr_employees', 'id')],
            'type' => ['required', 'string', Rule::in(['annual', 'sick', 'emergency', 'unpaid', 'maternity', 'other'])],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
        ];
    }
}
