<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = (int) $this->route('product');

        return [
            'branch_id' => ['required', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tenant.products', 'sku')
                    ->where('branch_id', (int) $this->input('branch_id'))
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
