<?php

namespace App\Http\Requests\Tenant\Hr\Department;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = (int) $this->route('department');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('tenant.hr_departments', 'name')->ignore($departmentId)],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
