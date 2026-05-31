<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $features = $this->relationLoaded('features')
            ? PlanFeatureResource::collection($this->features)->resolve()
            : [];

        $featureMap = [];
        foreach ($this->features ?? [] as $feature) {
            $featureMap[$feature->feature_key] = $feature->feature_value;
        }

        $enabledFeatures = collect($features)
            ->filter(fn (array $feature): bool => str_ends_with($feature['feature_key'], '.enabled')
                && in_array(strtolower((string) $feature['feature_value']), ['1', 'true', 'yes', 'enabled'], true))
            ->pluck('feature_key')
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->name,
            'description' => $this->description,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'billing_cycle' => $this->billing_cycle,
            'duration_days' => (int) ($this->duration_days ?? 365),
            'days' => (int) ($this->duration_days ?? 365),
            'status' => $this->status,
            'is_active' => $this->status === 'active',
            'sort_order' => (int) ($this->sort_order ?? 0),
            'features' => $features,
            'feature_map' => $featureMap,
            'enabled_features' => $enabledFeatures,
            'features_count' => count($enabledFeatures),
            'tenants_count' => $this->whenCounted('tenants'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
