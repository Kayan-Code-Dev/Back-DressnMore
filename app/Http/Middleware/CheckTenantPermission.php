<?php

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantPermission
{
    /**
     * @var array<string, list<string>>
     */
    private const LEGACY_ALIASES = [
        'accounting.journal_entries.view' => ['accounting.view'],
        'accounting.journal_entries.export' => ['accounting.view'],
    ];

    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized();
        }

        if ($this->userHasPermission($user, $permissionKey)) {
            return $next($request);
        }

        foreach (self::LEGACY_ALIASES[$permissionKey] ?? [] as $alias) {
            if ($this->userHasPermission($user, $alias)) {
                return $next($request);
            }
        }

        return ApiResponse::forbidden();
    }

    private function userHasPermission(User $user, string $permissionKey): bool
    {
        return $user->roles()
            ->whereHas('permissions', function ($query) use ($permissionKey): void {
                $query->where('key', $permissionKey);
            })
            ->exists();
    }
}
