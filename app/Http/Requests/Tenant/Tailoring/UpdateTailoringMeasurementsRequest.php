<?php

namespace App\Http\Requests\Tenant\Tailoring;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTailoringMeasurementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'measurements' => ['required', 'array'],
            'measurements.*.label' => ['required', 'string', 'max:120'],
            'measurements.*.value' => ['required', 'string', 'max:120'],
            'measurements.*.unit' => ['nullable', 'string', 'max:20'],
        ];
    }
}
