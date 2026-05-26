<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantDatabaseManager;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SetTenantDatabase
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantDatabaseManager $tenantDatabaseManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        try {
            $this->tenantDatabaseManager->connect($tenant);
        } catch (Throwable $exception) {
            Log::error('tenant.database.connection_failed', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::error('Tenant database connection failed', 500);
        }

        return $next($request);
    }
}
