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
     *     starts_at: string,
     *     expires_at: string|null,
     *     can_renew: bool,
     *     days_remaining: int|null
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');

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

        return [
            'account_type' => $isPaid ? 'paid' : 'free',
            'lifecycle_status' => $lifecycleStatus,
            'plan_code' => $plan?->slug ?? 'free',
            'plan_name' => $plan?->name ?? 'مجاني',
            'starts_at' => $startsAt->toDateString(),
            'expires_at' => $expiresAt?->toDateString(),
            'can_renew' => true,
            'days_remaining' => $daysRemaining,
        ];
    }
}
