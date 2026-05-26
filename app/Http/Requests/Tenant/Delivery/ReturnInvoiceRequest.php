<?php

namespace App\Http\Requests\Tenant\Delivery;

use App\Enums\DressStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'dress_status_after_return' => [
                'nullable',
                'string',
                Rule::in([
                    DressStatus::AVAILABLE->value,
                    DressStatus::MAINTENANCE->value,
                ]),
            ],
        ];
    }
}
