<?php

namespace App\Http\Requests\Tenant\Hr\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.hr_shifts', 'id')],
            'check_in' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out' => ['sometimes', 'nullable', 'date_format:H:i'],
            'late_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1440'],
            'overtime_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:24'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['present', 'absent', 'late', 'half_day', 'day_off', 'leave', 'holiday'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
