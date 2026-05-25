<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'check.tenant.subscription' => \App\Http\Middleware\CheckTenantSubscription::class,
            'set.tenant.database' => \App\Http\Middleware\SetTenantDatabase::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            if ($exception instanceof AuthorizationException) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($exception instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found'], 404);
            }

            if ($exception instanceof HttpExceptionInterface) {
                return response()->json(['message' => $exception->getMessage() ?: 'Request failed'], $exception->getStatusCode());
            }

            report($exception);

            return response()->json(['message' => 'Server error'], 500);
        });
    })->create();
