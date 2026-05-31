<?php

namespace App\Http\Requests\Platform\Plan;

use App\Support\PlanFeatureCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id ?? $this->route('plan');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('central.plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'string', 'max:30'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('features') || ! is_array($this->input('features'))) {
            return;
        }

        $features = [];
        foreach ($this->input('features') as $key => $value) {
            if (! is_string($key) || ! in_array($key, PlanFeatureCatalog::keys(), true)) {
                continue;
            }
            $features[$key] = $value;
        }

        $this->merge(['features' => $features]);
    }
}
