<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'product_sku' => $this->whenLoaded('product', fn () => $this->product?->sku),
            'from_branch_id' => $this->from_branch_id,
            'from_branch_name' => $this->whenLoaded('fromBranch', fn () => $this->fromBranch?->name),
            'to_branch_id' => $this->to_branch_id,
            'to_branch_name' => $this->whenLoaded('toBranch', fn () => $this->toBranch?->name),
            'quantity' => (int) $this->quantity,
            'scheduled_delivery_at' => $this->scheduled_delivery_at?->toISOString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'requested_by' => $this->requested_by,
            'requested_by_name' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->name),
            'confirmed_by' => $this->confirmed_by,
            'rejected_by' => $this->rejected_by,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
