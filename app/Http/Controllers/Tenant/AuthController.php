<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LoginRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Services\Auth\TenantAuthService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $tenantAuthService,
        private readonly TenantContext $tenantContext
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->tenantAuthService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString()
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
            ],
            'permissions' => $result['permissions'],
            'plan' => $result['plan'],
        ], 'Tenant login successful');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => new UserResource($request->user()),
            'tenant' => [
                'id' => $this->tenantContext->id(),
                'name' => $this->tenantContext->tenant()?->name,
                'slug' => $this->tenantContext->slug(),
            ],
            'permissions' => $this->tenantAuthService->permissionsForUser($request->user()),
            'plan' => $this->tenantContext->tenant()?->plan,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }
}
