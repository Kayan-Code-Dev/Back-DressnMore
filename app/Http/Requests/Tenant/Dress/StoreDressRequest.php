<?php

namespace App\Http\Requests\Tenant\Dress;

use App\Models\Tenant\Dress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dress_category_id' => ['nullable', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'dress_subcategory_id' => ['nullable', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:100', Rule::unique('tenant.dresses', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'size' => ['nullable', 'string', 'max:100'],
            'breast_size' => ['nullable', 'string', 'max:100'],
            'waist_size' => ['nullable', 'string', 'max:100'],
            'sleeve_size' => ['nullable', 'string', 'max:100'],
            'measurements' => ['nullable', 'array'],
            'color' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'delivery_date' => ['nullable', 'date'],
            'days_of_rent' => ['nullable', 'integer', 'min:1'],
            'occasion_datetime' => ['nullable', 'date'],
            'visit_datetime' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(Dress::statuses())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
