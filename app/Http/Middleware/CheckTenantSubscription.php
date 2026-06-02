<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        $status = (string) $tenant->status;
        if ($status !== 'active') {
            $message = match ($status) {
                'suspended' => 'Tenant is suspended',
                'expired' => 'Tenant subscription expired',
                'provisioning_failed' => 'Tenant provisioning failed',
                'provisioning' => 'Tenant is still provisioning',
                default => 'Tenant is not active',
            };

            return ApiResponse::error($message, 403);
        }

        if ($tenant->subscription_ends_at !== null) {
            $endsAt = CarbonImmutable::parse((string) $tenant->subscription_ends_at);
            if ($endsAt->lt(CarbonImmutable::today())) {
                return ApiResponse::error('Tenant subscription expired', 403);
            }
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        return $request->is('api/tenant/me')
            || $request->is('api/tenant/subscription')
            || $request->is('api/tenant/subscription/*')
            || $request->is('api/tenant/settings/password')
            || $request->is('api/tenant/settings/account')
            || $request->is('api/tenant/logout');
    }
}
