<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Tenant\RenewTenantRequest;
use App\Http\Requests\Platform\Tenant\StoreTenantRequest;
use App\Http\Resources\Platform\TenantResource;
use App\Models\Central\Tenant;
use App\Services\Platform\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $tenantProvisioningService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $tenants = $this->tenantProvisioningService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'plan_id' => $request->query('plan_id'),
        ], $perPage);

        return ApiResponse::paginated($tenants, TenantResource::collection($tenants->items())->resolve());
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->provision($request->validated());

        return ApiResponse::success(new TenantResource($tenant), 'Tenant provisioned', 201);
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->suspend($tenant);

        return ApiResponse::success(new TenantResource($tenant), 'Tenant suspended');
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->activate($tenant);

        return ApiResponse::success(new TenantResource($tenant), 'Tenant activated');
    }

    public function renew(RenewTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->renew($tenant, $request->validated());

        return ApiResponse::success(new TenantResource($tenant), 'Tenant renewed');
    }
}
