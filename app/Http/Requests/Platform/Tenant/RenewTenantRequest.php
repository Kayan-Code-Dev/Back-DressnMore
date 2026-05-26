<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RenewTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'subscription_ends_at' => ['nullable', 'date'],
        ];
    }
}
