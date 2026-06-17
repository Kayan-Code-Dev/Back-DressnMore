<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\LoginRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Services\Auth\TenantAuthService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use App\Support\TenantSubscriptionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $tenantAuthService,
        private readonly TenantContext $tenantContext,
        private readonly TenantSubscriptionPresenter $tenantSubscriptionPresenter,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->tenantAuthService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString()
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'account_type' => 'tenant',
            'user' => new UserResource($result['user']),
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
            ],
            'roles' => $result['roles'] ?? [],
            'permissions' => $result['permissions'],
            'endpoints' => $this->tenantEndpoints($request, $result['tenant']->slug),
            'subscription' => $this->tenantSubscriptionPresenter->forTenant($result['tenant']),
        ], 'Tenant login successful');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'account_type' => 'tenant',
            'user' => new UserResource($request->user()),
            'tenant' => [
                'id' => $this->tenantContext->id(),
                'name' => $this->tenantContext->tenant()?->name,
                'slug' => $this->tenantContext->slug(),
            ],
            'roles' => $this->tenantAuthService->rolesForUser($request->user()),
            'permissions' => $this->tenantAuthService->permissionsForUser($request->user()),
            'endpoints' => $this->tenantEndpoints($request, $this->tenantContext->slug()),
            'subscription' => $this->tenantSubscriptionPresenter->forTenant(
                $this->tenantContext->tenant()
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }

    /**
     * Build the integration endpoints contract consumed by the frontend
     * (drives API base URL, optional cross-subdomain redirect, and WebSocket host).
     *
     * @return array<string, string|null>
     */
    private function tenantEndpoints(Request $request, ?string $slug): array
    {
        $origin = $request->getSchemeAndHttpHost();

        return [
            'frontend_app_url' => config('app.frontend_url'),
            'backend_api_origin' => $origin,
            'backend_api_url' => $origin.'/api/tenant',
            'reverb_public_url' => $this->reverbPublicUrl(),
            'tenant_slug' => $slug,
        ];
    }

    private function reverbPublicUrl(): ?string
    {
        $host = env('REVERB_HOST');
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $scheme = (string) env('REVERB_SCHEME', 'https');
        $port = (string) env('REVERB_PORT', '443');

        return sprintf('%s://%s:%s', $scheme, $host, $port);
    }
}
