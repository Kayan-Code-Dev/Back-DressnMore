<?php

namespace App\Http\Requests\Tenant\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('tenant.suppliers', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(PurchaseOrderStatus::values())],
            'type' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'order_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
