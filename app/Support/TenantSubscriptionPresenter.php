<?php

namespace App\Support;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;

class TenantSubscriptionPresenter
{
    /**
     * @return array<string, mixed>
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
        if ($tenant->cancelled_at !== null) {
            $lifecycleStatus = 'cancelled';
        } elseif ($expiresAt !== null && $expiresAt->lt(CarbonImmutable::today())) {
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

        $currency = PlanCurrency::normalize($plan?->currency ?? 'EGP');

        return [
            'account_type' => $isPaid ? 'paid' : 'free',
            'lifecycle_status' => $lifecycleStatus,
            'plan_id' => $plan?->id,
            'plan_code' => $plan?->slug ?? 'free',
            'plan_name' => $plan?->name ?? 'مجاني',
            'price' => (float) ($plan?->price ?? 0),
            'currency' => $currency,
            'currency_symbol' => PlanCurrency::symbol($currency),
            'billing_cycle' => $plan?->billing_cycle ?? 'monthly',
            'starts_at' => $startsAt->toDateString(),
            'expires_at' => $expiresAt?->toDateString(),
            'can_renew' => $isPaid ? $lifecycleStatus !== 'cancelled' : true,
            'can_cancel' => $lifecycleStatus === 'active' || $lifecycleStatus === 'expired',
            'days_remaining' => $daysRemaining,
            'cancelled_at' => $tenant->cancelled_at?->toDateString(),
            'cancellation_reason' => $tenant->cancellation_reason,
            'features' => $features,
            'enabled_modules' => $enabledModules,
        ];
    }
}
