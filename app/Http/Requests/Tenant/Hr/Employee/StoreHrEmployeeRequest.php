<?php

namespace App\Http\Requests\Tenant\Hr\Employee;

use App\Enums\HrEmployeeStatus;
use App\Enums\HrEmploymentType;
use App\Enums\HrSalaryType;
use App\Http\Requests\Tenant\Hr\Employee\Concerns\ValidatesHrEmployeeUserAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHrEmployeeRequest extends FormRequest
{
    use ValidatesHrEmployeeUserAccount;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('tenant.hr_employees', 'employee_code')->whereNull('deleted_at')],
            'full_name' => ['required', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('tenant.hr_employees', 'national_id')->whereNull('deleted_at')],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('tenant.hr_departments', 'id')],
            'job_title_id' => ['nullable', 'integer', Rule::exists('tenant.hr_job_titles', 'id')],
            'employment_type' => ['required', 'string', Rule::in(HrEmploymentType::values())],
            'status' => ['nullable', 'string', Rule::in(HrEmployeeStatus::values())],
            'joining_date' => ['required', 'date'],
            'leaving_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'salary_type' => ['required', 'string', Rule::in(HrSalaryType::values())],
            'working_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ], $this->userAccountRules(true));
    }

    public function messages(): array
    {
        return $this->userAccountMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $account = (array) $this->input('user_account', []);
            $roleId = $account['role_id'] ?? null;
            $permissionIds = (array) ($account['permission_ids'] ?? []);

            if (! $roleId && $permissionIds === []) {
                $validator->errors()->add(
                    'user_account.role_id',
                    'اختر دوراً جاهزاً أو حدد صلاحيات مخصصة.'
                );
            }
        });
    }
}
