<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);

        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    });
});
