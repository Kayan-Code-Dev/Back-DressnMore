<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_code' => $this->branch_code ?: $this->code,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'vat_enabled' => (bool) $this->vat_enabled,
            'vat_type' => $this->vat_type,
            'vat_value' => $this->vat_value,
            'currency' => $this->currency,
            'currency_id' => $this->currency_id,
            'street' => $this->street,
            'building' => $this->building,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'notes' => $this->notes,
            'inventory_name' => $this->inventory_name,
            'image' => $this->image,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
