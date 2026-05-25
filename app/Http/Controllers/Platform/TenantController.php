<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Resources\Platform\TenantResource;
use App\Models\Central\Tenant;
use App\Services\Tenant\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class TenantController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $tenantProvisioningService)
    {
    }

    public function index(): JsonResponse
    {
        $tenants = Tenant::query()->latest('id')->paginate(20);

        return ApiResponse::success(TenantResource::collection($tenants));
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return ApiResponse::success(new TenantResource($tenant));
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        try {
            $result = $this->tenantProvisioningService->provision($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Tenant provisioning failed', 500);
        }

        return ApiResponse::success([
            'tenant' => new TenantResource($result['tenant']),
            'workspace' => $result['workspace'],
            'owner_email' => $result['owner_email'],
        ], 'Tenant provisioned successfully', 201);
    }
}
