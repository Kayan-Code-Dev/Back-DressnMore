<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Support\PlanCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class TenantPlanChangeRequestService
{
    public function __construct(
        private readonly PlanRequestPaymentProofService $planRequestPaymentProofService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submit(Tenant $tenant, User $user, array $data): array
    {
        $plan = Plan::query()
            ->where('slug', (string) ($data['plan_code'] ?? ''))
            ->where('status', 'active')
            ->first();

        if ($plan === null) {
            throw new RuntimeException('الباقة المطلوبة غير متاحة');
        }

        $currentPlan = $tenant->plan;
        $requestType = $this->resolveRequestType($tenant, $plan, $currentPlan);

        if ($requestType === 'same') {
            throw new RuntimeException('هذه هي باقتك الحالية بالفعل');
        }

        if ($requestType === 'free_renew') {
            throw new RuntimeException('الباقة المجانية تستخدم التجديد المباشر');
        }

        $hasPending = PlanRequest::query()
            ->where('source_tenant_id', $tenant->id)
            ->whereIn('request_type', ['upgrade', 'downgrade', 'renew'])
            ->where('status', 'payment_submitted')
            ->exists();

        if ($hasPending) {
            throw new RuntimeException('يوجد طلب اشتراك قيد المراجعة بالفعل');
        }

        $this->assertPaymentPayload($data);

        $planRequest = PlanRequest::query()->create([
            'request_type' => $requestType,
            'source_tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'old_plan_id' => $currentPlan?->id,
            'name' => $user->name,
            'email' => strtolower(trim((string) $user->email)),
            'phone' => trim((string) ($user->phone ?? '')),
            'password' => Hash::make(Str::random(32)),
            'company_name' => $tenant->name,
            'payment_gateway_id' => (int) $data['payment_gateway_id'],
            'tenant_notes' => trim((string) ($data['tenant_notes'] ?? '')) ?: null,
            'billing_cycle' => $plan->billing_cycle ?? 'monthly',
            'status' => 'payment_submitted',
        ]);

        $proofPath = $this->attachPaymentProof($planRequest, $data);

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'plan_request_id' => $planRequest->id,
            'payment_gateway_id' => (int) $data['payment_gateway_id'],
            'purpose' => 'subscription_'.$requestType,
            'amount' => $plan->price,
            'currency' => PlanCurrency::normalize($plan->currency ?? 'EGP'),
            'method' => (string) ($data['payment_method'] ?? 'manual'),
            'reference' => trim((string) $data['payment_reference']),
            'proof_path' => $proofPath,
            'status' => 'pending',
            'notes' => 'Plan request #'.$planRequest->id,
        ]);

        $messages = [
            'upgrade' => 'تم إرسال طلب ترقية الباقة بنجاح. سيتم مراجعته من الإدارة بعد التأكد من التحويل.',
            'downgrade' => 'تم إرسال طلب تخفيض الباقة. سيتم تطبيقه بعد موافقة الإدارة (السعر الكامل بدون تناسب).',
            'renew' => 'تم إرسال طلب تجديد الاشتراك. سيتم تمديد الفترة بعد موافقة الإدارة.',
        ];

        return [
            'request_id' => $planRequest->id,
            'request_type' => $requestType,
            'status' => 'payment_submitted',
            'message' => $messages[$requestType] ?? 'تم إرسال الطلب بنجاح.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingForTenant(Tenant $tenant): ?array
    {
        $planRequest = PlanRequest::query()
            ->with(['plan', 'oldPlan'])
            ->where('source_tenant_id', $tenant->id)
            ->whereIn('request_type', ['upgrade', 'downgrade', 'renew'])
            ->where('status', 'payment_submitted')
            ->latest('id')
            ->first();

        if ($planRequest === null) {
            return null;
        }

        return [
            'request_id' => $planRequest->id,
            'request_type' => $planRequest->request_type,
            'status' => $planRequest->status,
            'plan_code' => $planRequest->plan?->slug,
            'plan_name' => $planRequest->plan?->name,
            'old_plan_name' => $planRequest->oldPlan?->name,
            'payment_submitted_at' => $planRequest->payment_submitted_at?->toISOString(),
            'message' => 'طلب الاشتراك قيد المراجعة. سيتم التفعيل بعد موافقة الإدارة.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(Tenant $tenant, int $perPage = 15): array
    {
        return PlanRequest::query()
            ->with(['plan', 'oldPlan', 'paymentGateway'])
            ->where('source_tenant_id', $tenant->id)
            ->whereIn('request_type', ['upgrade', 'downgrade', 'renew'])
            ->latest('id')
            ->limit($perPage)
            ->get()
            ->map(fn (PlanRequest $row): array => [
                'id' => $row->id,
                'request_type' => $row->request_type,
                'status' => $row->status,
                'plan_code' => $row->plan?->slug,
                'plan_name' => $row->plan?->name,
                'old_plan_name' => $row->oldPlan?->name,
                'amount' => (float) ($row->plan?->price ?? 0),
                'payment_submitted_at' => $row->payment_submitted_at?->toISOString(),
                'approved_at' => $row->approved_at?->toISOString(),
                'created_at' => $row->created_at?->toISOString(),
            ])
            ->all();
    }

    public function cancelOrder(Tenant $tenant, int $orderId): void
    {
        $planRequest = PlanRequest::query()
            ->where('source_tenant_id', $tenant->id)
            ->whereKey($orderId)
            ->firstOrFail();

        if (! in_array($planRequest->status, ['pending', 'payment_submitted'], true)) {
            throw new RuntimeException('لا يمكن إلغاء هذا الطلب');
        }

        $planRequest->update(['status' => 'rejected', 'admin_notes' => 'ألغاه المستأجر']);

        Payment::query()
            ->where('plan_request_id', $planRequest->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function resolveRequestType(Tenant $tenant, Plan $target, ?Plan $current): string
    {
        if ($current === null) {
            return 'upgrade';
        }

        if ((int) $current->id === (int) $target->id) {
            return (float) $target->price > 0 ? 'renew' : 'same';
        }

        if ((float) $target->price <= 0) {
            return 'free_renew';
        }

        $currentOrder = (int) ($current->sort_order ?? 0);
        $targetOrder = (int) ($target->sort_order ?? 0);

        if ($targetOrder > $currentOrder || (float) $target->price > (float) $current->price) {
            return 'upgrade';
        }

        return 'downgrade';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPaymentPayload(array $data): void
    {
        if (empty($data['payment_gateway_id'])) {
            throw new RuntimeException('بوابة الدفع مطلوبة');
        }

        if (trim((string) ($data['payment_reference'] ?? '')) === '') {
            throw new RuntimeException('رقم المحفظة أو الحساب الذي دفعت منه مطلوب');
        }

        if (! ($data['payment_proof'] ?? null) instanceof UploadedFile) {
            throw new RuntimeException('صورة إيصال التحويل مطلوبة');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachPaymentProof(PlanRequest $planRequest, array $data): string
    {
        /** @var UploadedFile $paymentProof */
        $paymentProof = $data['payment_proof'];
        $proofPath = $this->planRequestPaymentProofService->store($paymentProof, $planRequest->id);

        $planRequest->update([
            'payment_reference' => trim((string) $data['payment_reference']),
            'payment_proof_path' => $proofPath,
            'payment_submitted_at' => CarbonImmutable::now(),
        ]);

        return $proofPath;
    }
}
