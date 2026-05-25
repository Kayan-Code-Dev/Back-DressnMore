<?php

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $hasPermission = $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();

        if (! $hasPermission) {
            return ApiResponse::error('Forbidden', 403);
        }

        return $next($request);
    }
}
