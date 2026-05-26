<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DressCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'parent' => $this->whenLoaded('parent', function (): ?array {
                if ($this->parent === null) {
                    return null;
                }

                return [
                    'id' => $this->parent->id,
                    'parent_id' => $this->parent->parent_id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug,
                    'status' => $this->parent->status,
                ];
            }),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(
                fn ($child): array => [
                    'id' => $child->id,
                    'parent_id' => $child->parent_id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'status' => $child->status,
                ]
            )->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
