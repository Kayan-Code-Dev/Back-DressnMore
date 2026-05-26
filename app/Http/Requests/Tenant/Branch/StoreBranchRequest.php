<?php

namespace App\Http\Requests\Tenant\Branch;

use App\Enums\CustomerStatus;
use App\Enums\VatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tenant.branches', 'branch_code')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_enabled' => ['nullable', 'boolean'],
            'vat_type' => ['nullable', 'string', Rule::in(VatType::values())],
            'vat_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['nullable', 'integer'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'inventory_name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
