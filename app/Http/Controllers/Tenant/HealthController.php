<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->requireTenant();
        $tenantConnection = false;

        try {
            DB::connection('tenant')->select('SELECT 1');
            $tenantConnection = true;
        } catch (Throwable) {
            $tenantConnection = false;
        }

        return ApiResponse::success([
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'tenant_database_name' => $tenant->database_name,
            'tenant_database_connection' => $tenantConnection,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
