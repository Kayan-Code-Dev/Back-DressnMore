<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->input('workspace')
            ?? $request->header('X-Tenant')
            ?? $request->query('tenant');

        if (! is_string($workspace) || trim($workspace) === '') {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        $tenant = Tenant::query()
            ->where('slug', trim($workspace))
            ->first();

        if ($tenant === null) {
            return ApiResponse::error('Tenant not found', 404);
        }

        $this->tenantContext->setTenant($tenant);

        return $next($request);
    }
}
