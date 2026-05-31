<?php

namespace App\Http\Requests\Platform\PaymentGateway;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_holder' => $this->input('account_holder', $this->input('accountHolder')),
            'account_number' => $this->input('account_number', $this->input('accountNumber')),
            'bank_name' => $this->input('bank_name', $this->input('bankName')),
            'is_active' => $this->input('is_active', $this->input('isActive', true)),
            'display_order' => $this->input('display_order', $this->input('displayOrder', 1)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:190'],
            'account_number' => ['required', 'string', 'max:190'],
            'bank_name' => ['nullable', 'string', 'max:190'],
            'iban' => ['nullable', 'string', 'max:190'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
