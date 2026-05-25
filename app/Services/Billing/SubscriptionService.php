<?php

namespace App\Services\Billing;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;

class SubscriptionService
{
    public function activeForTenant(Tenant $tenant): ?Subscription
    {
        return Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>=', CarbonImmutable::now())
            ->with('plan')
            ->latest('ends_at')
            ->first();
    }

    public function isSubscriptionExpired(Tenant $tenant): bool
    {
        return $this->activeForTenant($tenant) === null;
    }
}
