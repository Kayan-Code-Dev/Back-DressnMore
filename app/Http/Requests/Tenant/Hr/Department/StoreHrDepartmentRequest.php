<?php

namespace App\Http\Requests\Tenant\Hr\Department;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('tenant.hr_departments', 'name')],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
