<?php

namespace App\Http\Requests\Tenant\Hr\Employee;

use App\Enums\HrEmployeeStatus;
use App\Enums\HrEmploymentType;
use App\Enums\HrSalaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = (int) $this->route('employee');

        return [
            'employee_code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('tenant.hr_employees', 'employee_code')->ignore($employeeId)->whereNull('deleted_at')],
            'full_name' => ['sometimes', 'required', 'string', 'max:190'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('tenant.hr_employees', 'national_id')->ignore($employeeId)->whereNull('deleted_at')],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['sometimes', 'nullable', 'string'],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.branches', 'id')],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.hr_departments', 'id')],
            'job_title_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.hr_job_titles', 'id')],
            'employment_type' => ['sometimes', 'required', 'string', Rule::in(HrEmploymentType::values())],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(HrEmployeeStatus::values())],
            'joining_date' => ['sometimes', 'required', 'date'],
            'leaving_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:joining_date'],
            'base_salary' => ['sometimes', 'required', 'numeric', 'min:0'],
            'salary_type' => ['sometimes', 'required', 'string', Rule::in(HrSalaryType::values())],
            'working_hours_per_day' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:24'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
