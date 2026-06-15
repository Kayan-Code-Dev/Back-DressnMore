<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Subscription\RenewSubscriptionRequest;
use App\Http\Requests\Tenant\Subscription\SubmitSubscriptionChangeRequest;
use App\Http\Requests\Tenant\Subscription\UpgradeSubscriptionRequest;
use App\Services\Platform\TenantPlanChangeRequestService;
use App\Services\Platform\TenantSubscriptionBillingService;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use App\Support\TenantMessages;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantSubscriptionBillingService $billingService,
        private readonly TenantPlanChangeRequestService $changeRequestService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function overview(): JsonResponse
    {
        $tenant = $this->tenantContext->tenant();
        if ($tenant === null) {
            return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
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
                return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
            }

            $subscription = $this->billingService->renew($tenant, $request->validated());

            return ApiResponse::success($subscription, 'تم تجديد الاشتراك');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function submitChangeRequest(SubmitSubscriptionChangeRequest $request): JsonResponse
    {
        try {
            $tenant = $this->tenantContext->tenant();
            if ($tenant === null) {
                return ApiResponse::error(TenantMessages::CONTEXT_REQUIRED, 400);
            }

            $payload = $request->validated();
            $payload['payment_proof'] = $request->file('payment_proof');

            $result = $this->changeRequestService->submit(
                $tenant,
                $request->user(),
                $payload,
            );

            return ApiResponse::success($result, (string) ($result['message'] ?? 'تم إرسال الطلب'), 202);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function upgrade(UpgradeSubscriptionRequest $request): JsonResponse
    {
        return ApiResponse::error(
            'يرجى اختيار الباقة وإتمام الدفع وإرفاق إثبات التحويل لمراجعة الإدارة',
            422,
        );
    }
}
