<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        $plan = $tenant->plan;

        if ($plan === null) {
            return ApiResponse::forbidden('Plan is not assigned');
        }

        $feature = $plan->features()->where('feature_key', $featureKey)->first();

        if ($feature === null) {
            return ApiResponse::forbidden('Feature is not available');
        }

        $value = strtolower((string) $feature->feature_value);
        $enabled = in_array($value, ['1', 'true', 'yes', 'enabled'], true);

        if (! $enabled) {
            return ApiResponse::forbidden('Feature is not available');
        }

        return $next($request);
    }
}
