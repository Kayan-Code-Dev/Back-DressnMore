<?php

namespace App\Http\Requests\Tenant\ProductTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('tenant.products', 'id')->whereNull('deleted_at')],
            'from_branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'to_branch_id' => ['required', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'scheduled_delivery_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
