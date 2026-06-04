<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('central.tenants', 'slug')],
            'database_name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('central.tenants', 'database_name'),
            ],
            'plan_id' => ['required', 'integer', Rule::exists('central.plans', 'id')],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date', 'after_or_equal:subscription_starts_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
