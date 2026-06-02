<?php

namespace App\Http\Requests\Tenant\Hr\Employee;

use App\Enums\HrEmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchHrEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(HrEmployeeStatus::values())],
            'leaving_date' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === HrEmployeeStatus::TERMINATED->value),
                'nullable',
                'date',
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
