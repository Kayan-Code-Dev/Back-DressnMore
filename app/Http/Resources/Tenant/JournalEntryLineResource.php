<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntryLine */
class JournalEntryLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'account' => $this->account_name,
            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
            'description' => $this->description,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'cost_center_id' => $this->cost_center_id,
        ];
    }
}
