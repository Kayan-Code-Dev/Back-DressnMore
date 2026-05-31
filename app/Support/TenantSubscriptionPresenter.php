<?php

namespace App\Support;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;

class TenantSubscriptionPresenter
{
    /**
     * @return array{
     *     account_type: string,
     *     lifecycle_status: string,
     *     plan_code: string,
     *     plan_name: string,
     *     plan_id: int|null,
     *     starts_at: string,
     *     expires_at: string|null,
     *     can_renew: bool,
     *     days_remaining: int|null,
     *     features: array<string, string>,
     *     enabled_modules: list<string>
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $tenant->loadMissing(['plan.features']);

        /** @var Plan|null $plan */
        $plan = $tenant->plan;
        $startsAt = $tenant->subscription_starts_at !== null
            ? CarbonImmutable::parse((string) $tenant->subscription_starts_at)
            : CarbonImmutable::now();
        $expiresAt = $tenant->subscription_ends_at !== null
            ? CarbonImmutable::parse((string) $tenant->subscription_ends_at)
            : null;

        $isPaid = $plan !== null && (float) $plan->price > 0;
        $daysRemaining = null;
        if ($expiresAt !== null) {
            $daysRemaining = max(
                0,
                CarbonImmutable::today()->startOfDay()->diffInDays($expiresAt->startOfDay(), false)
            );
        }

        $lifecycleStatus = 'active';
        if ($expiresAt !== null && $expiresAt->lt(CarbonImmutable::today())) {
            $lifecycleStatus = 'expired';
        }

        $features = [];
        $enabledModules = [];
        foreach ($plan?->features ?? [] as $feature) {
            $features[$feature->feature_key] = (string) $feature->feature_value;
            if (str_ends_with($feature->feature_key, '.enabled')
                && PlanFeatureCatalog::isEnabledValue($feature->feature_value)) {
                $enabledModules[] = str_replace('.enabled', '', $feature->feature_key);
            }
        }

        return [
            'account_type' => $isPaid ? 'paid' : 'free',
            'lifecycle_status' => $lifecycleStatus,
            'plan_id' => $plan?->id,
            'plan_code' => $plan?->slug ?? 'free',
            'plan_name' => $plan?->name ?? 'مجاني',
            'starts_at' => $startsAt->toDateString(),
            'expires_at' => $expiresAt?->toDateString(),
            'can_renew' => true,
            'days_remaining' => $daysRemaining,
            'features' => $features,
            'enabled_modules' => $enabledModules,
        ];
    }
}
