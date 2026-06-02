<?php

namespace App\Http\Requests\Tenant\Hr\Employee;

use App\Enums\HrEmployeeStatus;
use App\Enums\HrEmploymentType;
use App\Enums\HrSalaryType;
use App\Http\Requests\Tenant\Hr\Employee\Concerns\ValidatesHrEmployeeUserAccount;
use App\Models\Tenant\HrEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHrEmployeeRequest extends FormRequest
{
    use ValidatesHrEmployeeUserAccount;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = (int) $this->route('employee');
        $employee = HrEmployee::query()->with('user')->find($employeeId);
        $userId = $employee?->user_id;

        $userEmailRule = ['sometimes', 'email', 'max:190'];
        if ($userId) {
            $userEmailRule[] = Rule::unique('tenant.users', 'email')->ignore($userId);
        } else {
            $userEmailRule[] = Rule::unique('tenant.users', 'email');
        }

        return array_merge([
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
            'user_account' => ['sometimes', 'array'],
            'user_account.email' => $userEmailRule,
            'user_account.password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'user_account.role_id' => ['nullable', 'integer', Rule::exists('tenant.roles', 'id')],
            'user_account.permission_ids' => ['nullable', 'array', 'min:1'],
            'user_account.permission_ids.*' => ['integer', Rule::exists('tenant.permissions', 'id')],
        ]);
    }

    public function messages(): array
    {
        return $this->userAccountMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('user_account')) {
                return;
            }

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
