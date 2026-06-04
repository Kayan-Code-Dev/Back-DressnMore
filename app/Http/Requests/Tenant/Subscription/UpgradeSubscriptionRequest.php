<?php

namespace App\Http\Requests\Tenant\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpgradeSubscriptionRequest extends FormRequest
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
            'plan_code' => ['required', 'string', 'max:120'],
            'payment_gateway_id' => ['nullable', 'integer', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'mock_payment_confirmed' => ['nullable', 'boolean'],
        ];
    }
}
