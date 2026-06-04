<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use App\Support\PlanFeatureGate;
use App\Support\TenantMessages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PlanFeatureGate $planFeatureGate,
    ) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
        }

        if ($tenant->plan === null && ! app()->environment('testing')) {
            return ApiResponse::forbidden('Plan is not assigned');
        }

        if (! $this->planFeatureGate->isEnabled($tenant, $featureKey)) {
            return ApiResponse::forbidden('Feature is not available');
        }

        return $next($request);
    }
}
