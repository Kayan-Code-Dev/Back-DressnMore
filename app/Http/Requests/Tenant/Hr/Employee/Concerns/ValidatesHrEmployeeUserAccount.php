<?php

namespace App\Http\Requests\Tenant\Hr\Employee\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesHrEmployeeUserAccount
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
                Rule::unique('tenant.users', 'email'),
            ],
            'user_account.password' => [$required ? 'required' : 'sometimes', 'string', 'min:8', 'confirmed'],
            'user_account.role_id' => ['nullable', 'integer', Rule::exists('tenant.roles', 'id')],
            'user_account.permission_ids' => ['nullable', 'array', 'min:1'],
            'user_account.permission_ids.*' => ['integer', Rule::exists('tenant.permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userAccountMessages(): array
    {
        return [
            'user_account.required' => 'حساب الدخول مطلوب عند إضافة موظف.',
            'user_account.email.required' => 'بريد تسجيل الدخول مطلوب.',
            'user_account.password.required' => 'كلمة المرور مطلوبة.',
            'user_account.password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'user_account.password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
