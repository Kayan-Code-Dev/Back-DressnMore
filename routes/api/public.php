<?php

use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlanRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/plans', [PlanController::class, 'publicIndex']);
    Route::get('/payment-gateways', [PlanRequestController::class, 'paymentGateways']);
    Route::post('/order-plans', [PlanRequestController::class, 'store']);
});
