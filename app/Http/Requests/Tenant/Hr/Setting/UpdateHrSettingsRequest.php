<?php

namespace App\Http\Requests\Tenant\Hr\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.attendance_rules' => ['sometimes', 'array'],
            'settings.payroll_rules' => ['sometimes', 'array'],
            'settings.leave_rules' => ['sometimes', 'array'],
            'settings.document_rules' => ['sometimes', 'array'],
        ];
    }
}
