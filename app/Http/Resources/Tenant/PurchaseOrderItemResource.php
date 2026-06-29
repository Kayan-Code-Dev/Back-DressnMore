<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'item_name' => $this->item_name,
            'item_code' => $this->item_code,
            'code' => $this->item_code,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => round((float) $this->unit_price, 2),
            'total' => round((float) $this->total, 2),
            'dress_category_id' => $this->dress_category_id,
            'dress_subcategory_id' => $this->dress_subcategory_id,
            'category' => $this->whenLoaded('category', fn () => new DressCategoryResource($this->category)),
            'subcategory' => $this->whenLoaded('subcategory', fn () => new DressCategoryResource($this->subcategory)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
