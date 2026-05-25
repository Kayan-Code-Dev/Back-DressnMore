<?php

namespace App\Http\Middleware;

use App\Services\Billing\PlanFeatureService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PlanFeatureService $planFeatureService
    ) {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        if (! $this->planFeatureService->hasFeature($tenant, $feature)) {
            return ApiResponse::error('This feature is not available in your current plan', 403);
        }

        return $next($request);
    }
}
