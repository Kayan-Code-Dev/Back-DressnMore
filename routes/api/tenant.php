<?php

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DressCategoryController;
use App\Http\Controllers\Tenant\DressController;
use App\Http\Controllers\Tenant\HealthController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\LookupController;
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
        Route::get('/lookups', [LookupController::class, 'index']);

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

        Route::prefix('/dress-categories')->group(function (): void {
            Route::get('/', [DressCategoryController::class, 'index'])
                ->middleware('tenant.permission:dress_categories.view');
            Route::post('/', [DressCategoryController::class, 'store'])
                ->middleware('tenant.permission:dress_categories.create');
            Route::get('/{dressCategory}', [DressCategoryController::class, 'show'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.view');
            Route::put('/{dressCategory}', [DressCategoryController::class, 'update'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.update');
            Route::delete('/{dressCategory}', [DressCategoryController::class, 'destroy'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.delete');
        });

        Route::prefix('/dresses')->group(function (): void {
            Route::get('/', [DressController::class, 'index'])
                ->middleware('tenant.permission:dresses.view');
            Route::post('/', [DressController::class, 'store'])
                ->middleware('tenant.permission:dresses.create');
            Route::get('/{dress}', [DressController::class, 'show'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.view');
            Route::put('/{dress}', [DressController::class, 'update'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.update');
            Route::delete('/{dress}', [DressController::class, 'destroy'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.delete');
            Route::get('/{dress}/inventory-movements', [DressController::class, 'inventoryMovements'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:inventory.view');
        });

        Route::prefix('/invoices')->group(function (): void {
            Route::get('/', [InvoiceController::class, 'index'])
                ->middleware('tenant.permission:invoices.view');
            Route::post('/', [InvoiceController::class, 'store'])
                ->middleware('tenant.permission:invoices.create');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.view');
            Route::put('/{invoice}', [InvoiceController::class, 'update'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.update');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.delete');

            Route::get('/{invoice}/payments', [InvoiceController::class, 'payments'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_payments.view');
            Route::post('/{invoice}/payments', [InvoiceController::class, 'addPayment'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_payments.create');
        });
    });
});
