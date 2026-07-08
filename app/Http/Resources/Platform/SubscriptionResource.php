<?php

namespace App\Http\Resources\Platform;

use App\Support\PlanCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->relationLoaded('plan') ? $this->plan : null;
        $tenant = $this->relationLoaded('tenant') ? $this->tenant : null;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->name,
                'description' => $plan->description,
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'days' => (int) ($plan->duration_days ?? 30),
                'currency' => PlanCurrency::normalize($plan->currency ?? 'EGP'),
                'currency_symbol' => PlanCurrency::symbol($plan->currency ?? 'EGP'),
            ] : null,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ] : null,
        ];
    }
}
