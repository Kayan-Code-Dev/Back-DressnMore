<?php

use App\Http\Middleware\CheckDressCategoryPlanFeature;
use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\CheckTenantPermission;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantTokenBinding;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SetTenantDatabase;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'branch.scope' => \App\Http\Middleware\BranchScope::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'identify.tenant' => IdentifyTenant::class,
            'check.tenant.subscription' => CheckTenantSubscription::class,
            'set.tenant.database' => SetTenantDatabase::class,
            'ensure.tenant.token' => EnsureTenantTokenBinding::class,
            'tenant.permission' => CheckTenantPermission::class,
            'plan.feature' => CheckPlanFeature::class,
            'plan.dress_category' => CheckDressCategoryPlanFeature::class,
        ]);

        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetTenantDatabase::class);
        $middleware->prependToPriorityList(SetTenantDatabase::class, CheckTenantSubscription::class);
        $middleware->prependToPriorityList(CheckTenantSubscription::class, IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error(
                    message: 'The given data was invalid.',
                    status: 422,
                    errors: $exception->errors(),
                );
            }

            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            if ($exception instanceof AuthorizationException) {
                return ApiResponse::error('Forbidden', 403);
            }

            if ($exception instanceof ModelNotFoundException) {
                return ApiResponse::error('Resource not found', 404);
            }

            if ($exception instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    message: $exception->getMessage() ?: 'Request failed',
                    status: $exception->getStatusCode(),
                );
            }

            report($exception);

            return ApiResponse::error('Server error', 500);
        });
    })->create();
