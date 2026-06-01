<?php

namespace App\Http\Requests\Tenant\DressCategory;

use App\Models\Tenant\DressCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDressCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('tenant.dress_categories', 'slug')->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(DressCategory::statuses())],
        ];
    }
}
