<?php

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\CustomerController;
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

        Route::prefix('/customers')->group(function (): void {
            Route::get('/', [CustomerController::class, 'index'])
                ->middleware('tenant.permission:customers.view');
            Route::post('/', [CustomerController::class, 'store'])
                ->middleware('tenant.permission:customers.create');
            Route::get('/{customer}', [CustomerController::class, 'show'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.view');
            Route::put('/{customer}', [CustomerController::class, 'update'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.update');
            Route::delete('/{customer}', [CustomerController::class, 'destroy'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.delete');
        });
    });
});
