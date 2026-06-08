<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'purchase_order_number' => $this->purchase_order_number,
            'status' => $this->status,
            'type' => $this->type,
            'is_returned' => (bool) $this->is_returned,
            'is_received' => (bool) $this->is_received,
            'returned_at' => $this->returned_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'return_notes' => $this->return_notes,
            'receive_notes' => $this->receive_notes,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'payment_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'remaining_payment' => $this->remaining_amount,
            'order_date' => $this->order_date?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'received_by' => $this->received_by,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'category' => $this->whenLoaded('category', fn () => new DressCategoryResource($this->category)),
            'subcategory' => $this->whenLoaded('subcategory', fn () => new DressCategoryResource($this->subcategory)),
            'items' => $this->whenLoaded('items', fn () => PurchaseOrderItemResource::collection($this->items)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
