<?php

use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\PaymentController;
use App\Http\Controllers\Platform\PaymentGatewayController;
use App\Http\Controllers\Platform\PlatformNotificationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlanRequestController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/plans/public', [PlanController::class, 'publicIndex']);

    // PUBLIC: Payment gateways & plan requests
    Route::get('/payment-gateways/public', [PlanRequestController::class, 'paymentGateways']);
    Route::post('/plan-requests', [PlanRequestController::class, 'store']);

    Route::middleware(['auth:sanctum', 'platform.admin'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/dashboard/subscription-stats', [DashboardController::class, 'subscriptionStats']);

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
        Route::post('/tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])
            ->whereNumber('tenant');
        Route::post('/tenants/{tenant}/seed', [TenantController::class, 'seed'])
            ->whereNumber('tenant');

        Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);
        Route::post('/payment-gateways', [PaymentGatewayController::class, 'store']);
        Route::get('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'show'])
            ->whereNumber('paymentGateway');
        Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])
            ->whereNumber('paymentGateway');
        Route::delete('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])
            ->whereNumber('paymentGateway');
        Route::post('/payment-gateways/{paymentGateway}/toggle-status', [PaymentGatewayController::class, 'toggleStatus'])
            ->whereNumber('paymentGateway');

        // Subscriptions
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show'])->whereNumber('id');
        Route::patch('/subscriptions/{id}', [SubscriptionController::class, 'update'])->whereNumber('id');
        Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])->whereNumber('id');

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');
        Route::post('/payments/{id}/mark-paid', [PaymentController::class, 'markPaid'])->whereNumber('id');
        Route::post('/payments/{id}/reject', [PaymentController::class, 'reject'])->whereNumber('id');
        Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])->whereNumber('id');

        // Order Plans (Plan Requests)
        Route::get('/order-plans', [PlanRequestController::class, 'index']);
        Route::get('/order-plans/{id}', [PlanRequestController::class, 'show']);
        Route::patch('/order-plans/{id}', [PlanRequestController::class, 'update']);
        Route::post('/order-plans/{id}/approve', [PlanRequestController::class, 'approve']);

        Route::prefix('/notifications')->group(function (): void {
            Route::get('/', [PlatformNotificationController::class, 'index']);
            Route::get('/stats', [PlatformNotificationController::class, 'stats']);
            Route::post('/read-all', [PlatformNotificationController::class, 'markAllRead']);
            Route::patch('/{notification}/read', [PlatformNotificationController::class, 'markRead'])
                ->whereNumber('notification');
            Route::delete('/{notification}', [PlatformNotificationController::class, 'destroy'])
                ->whereNumber('notification');
        });
        Route::post('/order-plans/{id}/reject', [PlanRequestController::class, 'reject']);
        Route::delete('/order-plans/{id}', [PlanRequestController::class, 'destroy']);
    });
});

