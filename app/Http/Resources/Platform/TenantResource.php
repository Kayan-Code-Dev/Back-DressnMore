<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'database_name' => $this->database_name,
            'tenancy_db_name' => $this->database_name,
            'status' => $this->status,
            'is_active' => $this->status === 'active',
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
            'trial_ends_at' => $this->subscription_ends_at?->toISOString(),
            'metadata' => $this->metadata,
            'email' => $metadata['admin_email'] ?? null,
            'admin_email' => $metadata['admin_email'] ?? null,
            'admin_name' => $metadata['admin_name'] ?? null,
            'phone' => $metadata['phone'] ?? null,
            'domains' => TenantDomainResource::collection($this->whenLoaded('domains')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
