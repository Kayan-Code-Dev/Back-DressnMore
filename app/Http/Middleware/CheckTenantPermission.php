<?php

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantPermission
{
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized();
        }

        $hasPermission = $user->roles()
            ->whereHas('permissions', function ($query) use ($permissionKey): void {
                $query->where('key', $permissionKey);
            })
            ->exists();

        if (! $hasPermission) {
            return ApiResponse::forbidden();
        }

        return $next($request);
    }
}
