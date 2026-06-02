<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantResolver;
use App\Support\ApiResponse;
use App\Support\TenantMessages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $slug = $this->tenantResolver->resolveSlug($request);

        if ($slug === null) {
            return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->first();

        if ($tenant === null) {
            return ApiResponse::error(TenantMessages::NOT_FOUND, 404);
        }

        $this->tenantContext->setTenant($tenant);

        return $next($request);
    }
}
