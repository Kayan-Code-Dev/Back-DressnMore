<?php

namespace App\Http\Requests\Tenant\DressCategory;

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
        ];
    }
}
