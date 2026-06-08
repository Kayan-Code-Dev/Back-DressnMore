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
            'code' => $this->code,
            'dress_category_id' => $this->dress_category_id,
            'dress_subcategory_id' => $this->dress_subcategory_id,
            'dress_id' => $this->dress_id,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'dress' => $this->whenLoaded('dress', fn () => new DressResource($this->dress)),
            'category' => $this->whenLoaded('category', fn () => new DressCategoryResource($this->category)),
            'subcategory' => $this->whenLoaded('subcategory', fn () => new DressCategoryResource($this->subcategory)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
