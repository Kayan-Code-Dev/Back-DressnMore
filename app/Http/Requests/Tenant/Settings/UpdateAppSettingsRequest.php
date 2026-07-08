<?php

namespace App\Http\Requests\Tenant\Settings;

use App\Support\PlanCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', Rule::in(PlanCurrency::SUPPORTED)],
            'timezone' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
