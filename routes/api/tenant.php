<?php

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DressController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\SettingController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')->group(function (): void {
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
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('tenant.permission:dashboard.view');

        Route::apiResource('/users', UserController::class);
        Route::apiResource('/roles', RoleController::class);
        Route::apiResource('/branches', BranchController::class);
        Route::apiResource('/employees', EmployeeController::class);
        Route::apiResource('/customers', CustomerController::class);
        Route::apiResource('/dresses', DressController::class);
        Route::apiResource('/invoices', InvoiceController::class);
        Route::apiResource('/suppliers', SupplierController::class);
        Route::apiResource('/settings', SettingController::class)->only(['index', 'store', 'update']);
    });
});
