<?php

namespace App\Http\Middleware;

use App\Models\Central\PersonalAccessToken;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantTokenBinding
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user instanceof TenantUser) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken) {
            return $next($request);
        }

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        if ($token->tenant_id === null || (int) $token->tenant_id !== (int) $tenant->id) {
            return ApiResponse::error('Token is not valid for this tenant workspace', 403);
        }

        return $next($request);
    }
}
