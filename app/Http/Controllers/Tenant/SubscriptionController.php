<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Subscription\RenewSubscriptionRequest;
use App\Http\Requests\Tenant\Subscription\UpgradeSubscriptionRequest;
use App\Services\Platform\TenantSubscriptionBillingService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantSubscriptionBillingService $billingService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function overview(): JsonResponse
    {
        $tenant = $this->tenantContext->tenant();
        if ($tenant === null) {
            return ApiResponse::error('Tenant workspace is required', 400);
        }

        return ApiResponse::success($this->billingService->overview($tenant));
    }

    public function paymentGateways(): JsonResponse
    {
        return ApiResponse::success($this->billingService->activePaymentGateways());
    }

    public function renew(RenewSubscriptionRequest $request): JsonResponse
    {
        try {
            $tenant = $this->tenantContext->tenant();
            if ($tenant === null) {
                return ApiResponse::error('Tenant workspace is required', 400);
            }

            $subscription = $this->billingService->renew($tenant, $request->validated());

            return ApiResponse::success($subscription, 'تم تجديد الاشتراك');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function upgrade(UpgradeSubscriptionRequest $request): JsonResponse
    {
        try {
            $tenant = $this->tenantContext->tenant();
            if ($tenant === null) {
                return ApiResponse::error('Tenant workspace is required', 400);
            }

            $subscription = $this->billingService->upgrade($tenant, $request->validated());

            return ApiResponse::success($subscription, 'تم تحديث الباقة بنجاح');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }
}
