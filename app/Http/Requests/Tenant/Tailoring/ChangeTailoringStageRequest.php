<?php

namespace App\Http\Requests\Tenant\Tailoring;

use App\Enums\TailoringProductionStage;
use App\Enums\TailoringProductionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTailoringStageRequest extends FormRequest
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
            'to_stage' => ['required', 'string', Rule::in(TailoringProductionStage::values())],
            'to_status' => ['nullable', 'string', Rule::in(TailoringProductionStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
