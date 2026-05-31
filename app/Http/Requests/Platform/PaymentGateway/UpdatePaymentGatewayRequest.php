<?php

namespace App\Http\Requests\Platform\PaymentGateway;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];
        if ($this->has('accountHolder')) {
            $payload['account_holder'] = $this->input('accountHolder');
        }
        if ($this->has('accountNumber')) {
            $payload['account_number'] = $this->input('accountNumber');
        }
        if ($this->has('bankName')) {
            $payload['bank_name'] = $this->input('bankName');
        }
        if ($this->has('isActive')) {
            $payload['is_active'] = $this->input('isActive');
        }
        if ($this->has('displayOrder')) {
            $payload['display_order'] = $this->input('displayOrder');
        }
        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:190'],
            'type' => ['sometimes', 'string', 'max:50'],
            'account_holder' => ['sometimes', 'string', 'max:190'],
            'account_number' => ['sometimes', 'string', 'max:190'],
            'bank_name' => ['nullable', 'string', 'max:190'],
            'iban' => ['nullable', 'string', 'max:190'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
