<?php

namespace App\Http\Requests\Platform\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                Rule::unique('central.tenant_domains', 'domain'),
            ],
        ];
    }
}
