<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionService $subscriptionService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        if ($tenant->status === 'suspended') {
            return ApiResponse::error('Tenant is suspended', 403);
        }

        if ($tenant->status !== 'active') {
            return ApiResponse::error('Subscription expired', 403);
        }

        $activeSubscription = $this->subscriptionService->activeForTenant($tenant);

        if ($activeSubscription === null) {
            return ApiResponse::error('Subscription expired', 403);
        }

        if (! $activeSubscription->plan || ! $activeSubscription->plan->is_active) {
            return ApiResponse::error('Plan inactive', 403);
        }

        $request->attributes->set('active_subscription', $activeSubscription);

        return $next($request);
    }
}
