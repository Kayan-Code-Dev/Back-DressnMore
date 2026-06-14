<?php

namespace App\Http\Requests\Platform\PlanRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StorePlanRequestRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:central.plan_requests,email'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['required', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'payment_gateway_id' => ['nullable', 'integer', 'exists:central.payment_gateways,id'],
        ];
    }
}
