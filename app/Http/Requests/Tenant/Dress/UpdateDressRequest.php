<?php

namespace App\Http\Requests\Tenant\Dress;

use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
use App\Support\WesternDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => WesternDigits::normalize($this->input('code')),
            ]);
        }
    }

    public function rules(): array
    {
        $dressId = (int) $this->route('dress');

        return [
            'dress_category_id' => ['required', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'dress_subcategory_id' => ['required', 'integer', Rule::exists('tenant.dress_categories', 'id')->whereNull('deleted_at')],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tenant.dresses', 'code')
                    ->ignore($dressId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(Dress::statuses())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $categoryId = (int) $this->input('dress_category_id');
            $subcategoryId = (int) $this->input('dress_subcategory_id');

            if ($categoryId <= 0 || $subcategoryId <= 0) {
                return;
            }

            $category = DressCategory::query()->find($categoryId);
            if ($category !== null && $category->parent_id !== null) {
                $validator->errors()->add('dress_category_id', 'The selected category must be a parent category.');
            }

            $subcategory = DressCategory::query()->find($subcategoryId);
            if ($subcategory === null) {
                return;
            }

            if ($subcategory->parent_id === null) {
                $validator->errors()->add('dress_subcategory_id', 'The selected subcategory must belong to a parent category.');
            } elseif ((int) $subcategory->parent_id !== $categoryId) {
                $validator->errors()->add('dress_subcategory_id', 'The selected subcategory does not belong to the selected category.');
            }
        });
    }
}
