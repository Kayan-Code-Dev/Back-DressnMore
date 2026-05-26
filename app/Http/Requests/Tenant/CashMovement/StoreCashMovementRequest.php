<?php

namespace App\Http\Requests\Tenant\CashMovement;

use App\Enums\CashMovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Tenant\CashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    CashMovement::TYPE_MANUAL_ADJUSTMENT,
                    CashMovement::TYPE_INCOME,
                    CashMovement::TYPE_EXPENSE,
                ]),
            ],
            'direction' => ['required', 'string', Rule::in(CashMovementDirection::values())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'cashbox_id' => ['nullable', 'integer', Rule::exists('tenant.cashboxes', 'id')->whereNull('deleted_at')],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'movement_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
