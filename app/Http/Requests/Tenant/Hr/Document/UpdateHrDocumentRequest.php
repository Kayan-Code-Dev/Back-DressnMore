<?php

namespace App\Http\Requests\Tenant\Hr\Document;

use App\Enums\HrDocumentStatus;
use App\Enums\HrDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'required', 'integer', Rule::exists('tenant.hr_employees', 'id')],
            'document_type' => ['sometimes', 'required', 'string', Rule::in(HrDocumentType::values())],
            'file' => ['sometimes', 'required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'file_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_path' => ['prohibited'],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(HrDocumentStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
