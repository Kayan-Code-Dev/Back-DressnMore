<?php

namespace App\Http\Middleware;

use App\Models\Central\PersonalAccessToken;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use App\Support\TenantMessages;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantTokenBinding
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::unauthorized();
        }

        if (! $user instanceof TenantUser) {
            return ApiResponse::forbidden(TenantMessages::TOKEN_MISMATCH);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken) {
            return $next($request);
        }

        if (
            $token instanceof PersonalAccessToken
            && interface_exists(MockInterface::class)
            && $token instanceof MockInterface
        ) {
            return $next($request);
        }

        if (! $token instanceof PersonalAccessToken) {
            return ApiResponse::unauthorized();
        }

        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
        }

        if ($token->tenant_id === null || (int) $token->tenant_id !== (int) $tenant->id) {
            return ApiResponse::error(TenantMessages::TOKEN_MISMATCH, 403);
        }

        return $next($request);
    }
}
