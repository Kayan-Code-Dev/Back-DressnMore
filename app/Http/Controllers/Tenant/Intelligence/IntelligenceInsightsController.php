<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Intelligence;

use App\Http\Controllers\Controller;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolRegistry;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntelligenceInsightsController extends Controller
{
    private BusinessToolRegistry $registry;
    private TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->registry = BusinessToolRegistry::withStandardTools();
        $this->tenantContext = $tenantContext;
    }

    public function snapshot(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $result = $this->registry->execute('business_snapshot', $context);

        if ($result->isDenied()) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success($result->facts);
    }

    public function health(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $result = $this->registry->execute('business_health', $context);

        if ($result->isDenied()) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success($result->facts);
    }

    public function dailyBrief(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $result = $this->registry->execute('daily_brief', $context);

        if ($result->isDenied()) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success($result->facts);
    }

    private function buildContext(Request $request): BusinessToolContext
    {
        $user = $request->user();
        $tenant = $this->tenantContext->requireTenant();
        $tenantSlug = $tenant->slug ?? 'default';

        return BusinessToolContext::forUser($user, $tenantSlug);
    }
}
