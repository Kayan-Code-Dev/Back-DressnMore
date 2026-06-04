<?php

namespace App\Http\Requests\Tenant\Hr\Document;

use App\Enums\HrDocumentStatus;
use App\Enums\HrDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('tenant.hr_employees', 'id')],
            'document_type' => ['required', 'string', Rule::in(HrDocumentType::values())],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_path' => ['prohibited'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['nullable', 'string', Rule::in(HrDocumentStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
