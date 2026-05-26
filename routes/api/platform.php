<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'platform.admin'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/renew', [TenantController::class, 'renew'])
            ->whereNumber('tenant');
    });
});
