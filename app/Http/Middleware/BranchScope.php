<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically scopes requests to the authenticated user's branch.
 * Prevents employees from accessing or modifying other branches' data.
 */
class BranchScope
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Load the user's branch from hr_employee if not set on user
        $branchId = $user->branch_id;

        if ($branchId === null && $user->relationLoaded('hrEmployee')) {
            $branchId = $user->hrEmployee?->branch_id;
        }

        // Store the user's branch in the request for later use
        $request->attributes->set('user_branch_id', $branchId);

        // Auto-set branch_id on create requests if not provided
        if ($request->isMethod('post') && $branchId !== null) {
            $data = $request->all();

            // Only auto-set if branch_id is not explicitly provided
            if (!isset($data['branch_id']) || $data['branch_id'] === null) {
                $request->merge(['branch_id' => $branchId]);
            }

            // Prevent changing branch to a different one
            if (isset($data['branch_id']) && (int)$data['branch_id'] !== (int)$branchId) {
                // Owner can change branches, regular employees cannot
                $isOwner = $user->roles()->where('slug', 'owner')->exists();
                if (!$isOwner) {
                    $request->merge(['branch_id' => $branchId]);
                }
            }
        }

        // For non-owners, auto-filter by branch on list requests
        if ($branchId !== null && $request->isMethod('get')) {
            $isOwner = $user->roles()->where('slug', 'owner')->exists();
            if (!$isOwner && !$request->has('branch_id')) {
                $request->merge(['branch_id' => $branchId]);
            }
        }

        return $next($request);
    }
}
