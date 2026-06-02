<?php

namespace App\Http\Requests\Tenant\Hr\Shift;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHrShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['string', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
