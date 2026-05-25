<?php

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;

class PlanFeatureService
{
    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $subscription = app(SubscriptionService::class)->activeForTenant($tenant);
        if ($subscription === null || ! $subscription->plan) {
            return false;
        }

        $feature = $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->first();

        if ($feature === null) {
            return false;
        }

        $value = strtolower((string) $feature->feature_value);

        return in_array($value, ['1', 'true', 'yes', 'enabled'], true);
    }

    public function value(Plan $plan, string $featureKey): ?string
    {
        return $plan->features()
            ->where('feature_key', $featureKey)
            ->value('feature_value');
    }
}
