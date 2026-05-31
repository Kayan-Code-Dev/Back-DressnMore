<?php

namespace App\Http\Requests\Tenant\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'extension_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
