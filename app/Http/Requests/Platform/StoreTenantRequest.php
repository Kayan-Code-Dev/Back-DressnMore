<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', 'string', 'min:8'],
            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],
            'subscription_starts_at' => ['required', 'date'],
            'subscription_ends_at' => ['required', 'date', 'after:subscription_starts_at'],
        ];
    }
}
