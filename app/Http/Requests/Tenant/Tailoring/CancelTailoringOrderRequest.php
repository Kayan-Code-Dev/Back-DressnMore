<?php

namespace App\Http\Requests\Tenant\Tailoring;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelTailoringOrderRequest extends FormRequest
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
            'refund_customer' => ['nullable', 'boolean'],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'cashbox_id' => [
                'required_if:refund_customer,true',
                'nullable',
                'integer',
                Rule::exists('tenant.cashboxes', 'id')->whereNull('deleted_at'),
            ],
            'refund_method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('refund_customer')) {
            $this->merge([
                'refund_customer' => filter_var($this->input('refund_customer'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
