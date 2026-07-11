<?php

namespace App\Http\Requests\Tenant\Intelligence;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:' . config('intelligence.limits.max_input_chars', 2000)],
        ];
    }
}
