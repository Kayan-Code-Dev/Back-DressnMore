<?php

namespace App\Http\Requests\Tenant\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;

class CancelJournalEntryRequest extends FormRequest
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
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
