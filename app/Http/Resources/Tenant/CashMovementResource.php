<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'method' => $this->method,
            'direction' => $this->direction,
            'cashbox_id' => $this->cashbox_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference' => $this->reference,
            'movement_date' => $this->movement_date?->toISOString(),
            'description' => $this->description,
            'notes' => $this->notes,
            'is_reversed' => (bool) $this->is_reversed,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
