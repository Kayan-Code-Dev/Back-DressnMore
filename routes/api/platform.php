<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
