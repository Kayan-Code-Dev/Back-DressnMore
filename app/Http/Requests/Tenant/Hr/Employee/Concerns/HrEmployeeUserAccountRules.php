<?php

namespace App\Http\Requests\Tenant\Hr\Employee\Concerns;

use Illuminate\Validation\Rule;

trait HrEmployeeUserAccountRules
{
    /**
     * @return array<string, mixed>
     */
    protected function userAccountRules(bool $required): array
    {
        $prefix = $required ? 'required' : 'sometimes';

        return [
            'user_account' => [$prefix, 'array'],
            'user_account.email' => [
                $required ? 'required' : 'sometimes',
                'email',
                'max:190',
            ],
            'user_account.password' => [
                $required ? 'required' : 'sometimes',
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'user_account.role_id' => ['nullable', 'integer', Rule::exists('tenant.roles', 'id')],
            'user_account.permission_ids' => ['nullable', 'array'],
            'user_account.permission_ids.*' => ['integer', Rule::exists('tenant.permissions', 'id')],
        ];
    }
}
