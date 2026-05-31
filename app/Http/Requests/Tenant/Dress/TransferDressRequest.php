<?php

namespace App\Http\Requests\Tenant\Dress;

use Illuminate\Foundation\Http\FormRequest;

class TransferDressRequest extends FormRequest
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
            'to_branch_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
