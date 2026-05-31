<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'platform.admin'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/plans/feature-catalog', [PlanController::class, 'featureCatalog']);
        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::get('/plans/{plan}', [PlanController::class, 'show'])
            ->whereNumber('plan');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])
            ->whereNumber('plan');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])
            ->whereNumber('plan');

        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])
            ->whereNumber('tenant');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])
            ->whereNumber('tenant');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/migrate', [TenantController::class, 'migrate'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/seed', [TenantController::class, 'seed'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/domains', [TenantController::class, 'addDomain'])
            ->whereNumber('tenant');
        Route::delete('/tenants/{tenant}/domains/{domain}', [TenantController::class, 'deleteDomain'])
            ->whereNumber(['tenant', 'domain']);
        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/renew', [TenantController::class, 'renew'])
            ->whereNumber('tenant');
    });
});
