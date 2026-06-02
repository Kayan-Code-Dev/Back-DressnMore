<?php

namespace App\Http\Requests\Tenant\Hr\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrLeaveStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
