<?php

namespace App\Http\Middleware;

use App\Models\Tenant\DressCategory;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use App\Support\PlanFeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDressCategoryPlanFeature
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PlanFeatureGate $planFeatureGate,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        $featureKey = $this->resolveFeatureKey($request);

        if ($featureKey === null) {
            if ($this->planFeatureGate->isAnyEnabled($tenant, 'categories.enabled', 'subcategories.enabled')) {
                return $next($request);
            }

            return ApiResponse::forbidden('Feature is not available');
        }

        if (! $this->planFeatureGate->isEnabled($tenant, $featureKey)) {
            return ApiResponse::forbidden('Feature is not available');
        }

        return $next($request);
    }

    private function resolveFeatureKey(Request $request): ?string
    {
        $categoryId = $request->route()?->parameter('dressCategory');

        if ($categoryId !== null) {
            $category = DressCategory::query()->find($categoryId);

            if ($category === null) {
                return null;
            }

            return $category->parent_id !== null
                ? 'subcategories.enabled'
                : 'categories.enabled';
        }

        if ($request->isMethod('GET')) {
            if ($request->boolean('only_children')) {
                return 'subcategories.enabled';
            }

            if ($request->boolean('only_parents')) {
                return 'categories.enabled';
            }

            return null;
        }

        $parentId = $request->input('parent_id');

        if ($parentId !== null && $parentId !== '') {
            return 'subcategories.enabled';
        }

        return 'categories.enabled';
    }
}
