<?php

namespace App\Services\Platform;

use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
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

        if ((int) $tenant->plan_id === (int) $plan->id) {
            throw new RuntimeException('هذه هي باقتك الحالية بالفعل');
        }

        if ((float) $plan->price <= 0) {
            throw new RuntimeException('الباقة المجانية تستخدم التجديد المباشر');
        }

        $hasPending = PlanRequest::query()
            ->where('source_tenant_id', $tenant->id)
            ->where('request_type', 'upgrade')
            ->where('status', 'payment_submitted')
            ->exists();

        if ($hasPending) {
            throw new RuntimeException('يوجد طلب ترقية قيد المراجعة بالفعل');
        }

        $this->assertPaymentPayload($data);

        $planRequest = PlanRequest::query()->create([
            'request_type' => 'upgrade',
            'source_tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'name' => $user->name,
            'email' => strtolower(trim((string) $user->email)),
            'phone' => trim((string) ($user->phone ?? '')),
            'password' => Hash::make(Str::random(32)),
            'company_name' => $tenant->name,
            'payment_gateway_id' => (int) $data['payment_gateway_id'],
            'status' => 'payment_submitted',
        ]);

        $this->attachPaymentProof($planRequest, $data);

        return [
            'request_id' => $planRequest->id,
            'status' => 'payment_submitted',
            'message' => 'تم إرسال طلب ترقية الباقة بنجاح. سيتم مراجعته من الإدارة بعد التأكد من التحويل.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingForTenant(Tenant $tenant): ?array
    {
        $planRequest = PlanRequest::query()
            ->with('plan')
            ->where('source_tenant_id', $tenant->id)
            ->where('request_type', 'upgrade')
            ->where('status', 'payment_submitted')
            ->latest('id')
            ->first();

        if ($planRequest === null) {
            return null;
        }

        return [
            'request_id' => $planRequest->id,
            'status' => $planRequest->status,
            'plan_code' => $planRequest->plan?->slug,
            'plan_name' => $planRequest->plan?->name,
            'payment_submitted_at' => $planRequest->payment_submitted_at?->toISOString(),
            'message' => 'طلب ترقية الباقة قيد المراجعة. سيتم تفعيل الباقة الجديدة بعد موافقة الإدارة.',
        ];
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
    private function attachPaymentProof(PlanRequest $planRequest, array $data): void
    {
        /** @var UploadedFile $paymentProof */
        $paymentProof = $data['payment_proof'];
        $proofPath = $this->planRequestPaymentProofService->store($paymentProof, $planRequest->id);

        $planRequest->update([
            'payment_reference' => trim((string) $data['payment_reference']),
            'payment_proof_path' => $proofPath,
            'payment_submitted_at' => CarbonImmutable::now(),
        ]);
    }
}
