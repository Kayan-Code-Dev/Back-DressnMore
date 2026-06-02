<?php

namespace App\Http\Requests\Tenant\Hr\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('tenant.hr_employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', Rule::exists('tenant.hr_shifts', 'id')],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'half_day', 'day_off', 'leave', 'holiday'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
