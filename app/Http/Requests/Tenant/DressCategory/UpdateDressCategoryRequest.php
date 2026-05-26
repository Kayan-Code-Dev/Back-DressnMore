<?php

namespace App\Http\Requests\Tenant\DressCategory;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDressCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dressCategoryId = (int) $this->route('dressCategory');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.dress_categories', 'id')
                    ->where(fn ($query) => $query
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $dressCategoryId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenant.dress_categories', 'slug')
                    ->ignore($dressCategoryId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
