<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'type' => $this->type,
            'entry_type' => $this->type,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reference_number' => $this->reference_number,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'difference' => (float) $this->difference,
            'is_balanced' => (bool) $this->is_balanced,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'branch_name' => $this->branch?->name,
            'created_by' => $this->creator?->name,
            'created_by_id' => $this->created_by,
            'approved_by' => $this->approver?->name,
            'approved_by_id' => $this->approved_by,
            'cancelled_by' => $this->canceller?->name,
            'cancelled_by_id' => $this->cancelled_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'reversed_entry_id' => $this->reversed_entry_id,
            'reversed_entry' => $this->whenLoaded('reversedEntry', fn () => [
                'id' => $this->reversedEntry?->id,
                'entry_number' => $this->reversedEntry?->entry_number,
            ]),
            'lines_count' => $this->relationLoaded('lines') ? $this->lines->count() : null,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
