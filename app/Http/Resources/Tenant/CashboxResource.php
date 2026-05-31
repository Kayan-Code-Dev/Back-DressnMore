<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $initialBalance = round((float) ($this->initial_balance ?? 0), 2);
        $currentBalance = round((float) ($this->current_balance ?? 0), 2);
        $totalIn = round((float) ($this->total_in ?? 0), 2);
        $totalOut = round((float) ($this->total_out ?? 0), 2);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'manager_name' => null,
            'initial_balance' => $initialBalance,
            'current_balance' => $currentBalance,
            'balance_change' => round($currentBalance - $initialBalance, 2),
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
