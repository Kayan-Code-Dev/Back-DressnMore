<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dress_category_id' => $this->dress_category_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'size' => $this->size,
            'color' => $this->color,
            'purchase_price' => $this->purchase_price,
            'rental_price' => $this->rental_price,
            'sale_price' => $this->sale_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'category' => $this->whenLoaded('category', fn () => new DressCategoryResource($this->category)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
