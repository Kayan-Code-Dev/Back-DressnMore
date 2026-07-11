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
            'content' => [
                'required',
                'string',
                'min:1',
                'max:' . config('intelligence.limits.max_input_chars', 1500),
            ],
            'request_id' => ['nullable', 'string', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');
        if (is_string($content)) {
            $trimmed = trim($content);
            if ($trimmed === '') {
                $this->merge(['content' => '']);
            } else {
                $this->merge(['content' => $trimmed]);
            }
        }
    }
}
