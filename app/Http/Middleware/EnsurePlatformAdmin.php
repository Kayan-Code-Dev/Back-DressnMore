<?php

namespace App\Http\Middleware;

use App\Models\Central\SuperAdmin;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof SuperAdmin) {
            return ApiResponse::forbidden('Platform admin access required');
        }

        return $next($request);
    }
}
