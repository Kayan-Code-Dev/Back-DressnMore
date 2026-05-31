<?php

namespace App\Http\Requests\Tenant\JournalEntry;

use App\Models\Tenant\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends FormRequest
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
            'entry_date' => ['sometimes', 'date'],
            'type' => ['sometimes', Rule::in([
                JournalEntry::TYPE_NORMAL,
                JournalEntry::TYPE_ADJUSTMENT,
                JournalEntry::TYPE_OPENING,
                JournalEntry::TYPE_CLOSING,
                JournalEntry::TYPE_REVERSAL,
            ])],
            'branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'lines' => ['sometimes', 'array', 'min:2'],
            'lines.*.account_id' => ['required_with:lines', 'integer', 'exists:tenant.accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'lines.*.cost_center_id' => ['nullable', 'integer'],
        ];
    }
}
