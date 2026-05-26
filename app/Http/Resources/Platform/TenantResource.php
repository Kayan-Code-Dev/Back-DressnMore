<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'database_name' => $this->database_name,
            'status' => $this->status,
            'plan_id' => $this->plan_id,
            'plan' => $this->whenLoaded('plan', function (): ?array {
                if ($this->plan === null) {
                    return null;
                }

                return [
                    'id' => $this->plan->id,
                    'name' => $this->plan->name,
                    'slug' => $this->plan->slug,
                    'status' => $this->plan->status,
                ];
            }),
            'subscription_starts_at' => $this->subscription_starts_at?->toISOString(),
            'subscription_ends_at' => $this->subscription_ends_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
