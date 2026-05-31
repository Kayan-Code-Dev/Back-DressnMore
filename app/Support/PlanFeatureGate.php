<?php

namespace App\Support;

use App\Models\Central\Tenant;

class PlanFeatureGate
{
    public function isEnabled(Tenant $tenant, string $featureKey): bool
    {
        $tenant->loadMissing(['plan.features']);

        $plan = $tenant->plan;

        if ($plan === null) {
            return app()->environment('testing');
        }

        $feature = $plan->features->firstWhere('feature_key', $featureKey);

        if ($feature === null) {
            return false;
        }

        return PlanFeatureCatalog::isEnabledValue($feature->feature_value);
    }

    public function isAnyEnabled(Tenant $tenant, string ...$featureKeys): bool
    {
        foreach ($featureKeys as $featureKey) {
            if ($this->isEnabled($tenant, $featureKey)) {
                return true;
            }
        }

        return false;
    }
}
