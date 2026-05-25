<?php

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index'])
        ->middleware(['identify.tenant', 'check.tenant.subscription', 'set.tenant.database']);

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['identify.tenant', 'check.tenant.subscription', 'set.tenant.database']);

    Route::middleware([
        'identify.tenant',
        'check.tenant.subscription',
        'set.tenant.database',
        'auth:sanctum',
    ])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
