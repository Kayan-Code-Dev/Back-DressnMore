<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Tenant\AddDomainRequest;
use App\Http\Requests\Platform\Tenant\RenewTenantRequest;
use App\Http\Requests\Platform\Tenant\SeedTenantRequest;
use App\Http\Requests\Platform\Tenant\StoreTenantRequest;
use App\Http\Requests\Platform\Tenant\UpdateTenantRequest;
use App\Http\Resources\Platform\TenantDomainResource;
use App\Http\Resources\Platform\TenantResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantDomain;
use App\Services\Platform\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

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

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load(['plan', 'domains']);

        return ApiResponse::success(new TenantResource($tenant));
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->create($request->validated());

        return ApiResponse::success(new TenantResource($tenant), 'Tenant created', 201);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantProvisioningService->update($tenant, $request->validated());

        return ApiResponse::success(new TenantResource($tenant), 'Tenant updated');
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->tenantProvisioningService->destroy($tenant);

        return ApiResponse::success(null, 'Tenant deleted');
    }

    public function migrate(Tenant $tenant): JsonResponse
    {
        try {
            $tenant = $this->tenantProvisioningService->migrate($tenant);
        } catch (RuntimeException $exception) {
            return ApiResponse::serverError($exception->getMessage());
        }

        return ApiResponse::success(new TenantResource($tenant), 'Tenant migrated');
    }

    public function seed(SeedTenantRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $credentials = $this->tenantProvisioningService->seedAdmin($tenant, $request->validated());
        } catch (Throwable $exception) {
            return ApiResponse::serverError('Tenant seed failed: '.$exception->getMessage());
        }

        $tenant->refresh()->load(['plan', 'domains']);

        return ApiResponse::success([
            ...$credentials,
            'tenant' => (new TenantResource($tenant))->resolve(),
        ], 'Tenant admin seeded');
    }

    public function addDomain(AddDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $domain = $this->tenantProvisioningService->addDomain($tenant, (string) $request->validated('domain'));

        return ApiResponse::success(new TenantDomainResource($domain), 'Domain added', 201);
    }

    public function deleteDomain(Tenant $tenant, TenantDomain $domain): JsonResponse
    {
        try {
            $this->tenantProvisioningService->deleteDomain($tenant, $domain);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        return ApiResponse::success(null, 'Domain deleted');
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

    public function impersonate(int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);

        if ($tenantModel->status !== 'active') {
            return ApiResponse::error('لا يمكن الدخول إلى حساب Tenant غير فعال.', 403);
        }

        // Find the first active user of this tenant
        $tenantUser = \DB::connection("tenant")
            ->table("users")
            ->where("status", "active")
            ->first();

        if (!$tenantUser) {
            return ApiResponse::error('لا يوجد مستخدمون نشطون في هذا الـ Tenant.', 404);
        }

        // Load the tenant User model and create token
        $userModel = \App\Models\Tenant\User::on('tenant')->find($tenantUser->id);

        if (!$userModel) {
            return ApiResponse::error('لم يتم العثور على المستخدم في قاعدة بيانات Tenant.', 404);
        }

        // Delete old impersonation tokens
        $userModel->tokens()->where('name', 'impersonation')->delete();

        // Create new impersonation token
        $newToken = $userModel->createToken('impersonation', ['*'], now()->addHour());

        return ApiResponse::success([
            'tenant' => [
                'id' => $tenantModel->id,
                'name' => $tenantModel->name,
                'slug' => $tenantModel->slug,
            ],
            'user' => [
                'id' => $tenantUser->id,
                'name' => $tenantUser->name,
                'email' => $tenantUser->email,
            ],
            'token' => $newToken->plainTextToken,
            'redirect_url' => config('app.frontend_url', 'https://dressnmore.it.com') . '/?tenant=' . $tenantModel->slug,
        ], 'تم إنشاء رمز الدخول بنجاح.');
    }
}
