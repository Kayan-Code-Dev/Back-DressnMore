<?php

namespace App\Http\Requests\Tenant\Hr\JobTitle;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrJobTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.hr_departments', 'id')],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
