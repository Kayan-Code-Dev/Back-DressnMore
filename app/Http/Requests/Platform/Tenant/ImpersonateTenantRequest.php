<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ImpersonateTenantRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
