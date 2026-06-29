<?php

namespace App\Services\Platform;

use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PlanRequestService
{
    public function __construct(
        private readonly PlanRequestApprovalService $planRequestApprovalService,
        private readonly PlanRequestPaymentProofService $planRequestPaymentProofService,
        private readonly PlatformNotificationService $platformNotifier,
    ) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PlanRequest::query()
            ->with(['plan', 'oldPlan', 'paymentGateway', 'tenant', 'sourceTenant', 'payment'])
            ->latest('id');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$wildcard]);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(array $data): array
    {
        $plan = Plan::query()->findOrFail((int) $data['plan_id']);
        $isFreePlan = (float) $plan->price <= 0;
        $email = strtolower(trim((string) $data['email']));

        $existing = PlanRequest::query()->where('email', $email)->first();
        if ($existing !== null) {
            return $this->handleExistingRequest($existing, $data, $plan, $isFreePlan);
        }

        if (! $isFreePlan) {
            $this->assertPaidPlanPaymentPayload($data);
        }

        $plainPassword = (string) $data['password'];

        $planRequest = PlanRequest::query()->create([
            'request_type' => 'signup',
            'plan_id' => $plan->id,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'],
            'password' => Hash::make($plainPassword),
            'provision_password' => Crypt::encryptString($plainPassword),
            'company_name' => $data['company_name'] ?? null,
            'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
            'status' => $isFreePlan ? 'pending' : 'payment_submitted',
        ]);

        if (! $isFreePlan) {
            $this->attachPaymentProof($planRequest, $data);
        }

        if (! $isFreePlan) {
            $this->platformNotifier->notifyAllAdmins(
                'طلب اشتراك جديد',
                sprintf('طلب جديد من %s للخطة %s بانتظار المراجعة.', $planRequest->name, $plan->name),
                'system',
                'high',
                '/order-plans/'.$planRequest->id,
            );
        }

        if ($isFreePlan) {
            $approval = $this->planRequestApprovalService->approve($planRequest);

            return [
                'request_id' => $planRequest->id,
                'status' => 'approved',
                'auto_provisioned' => true,
                'message' => 'تم إنشاء حسابك بنجاح. يمكنك تسجيل الدخول الآن.',
                'tenant' => [
                    'id' => $approval['tenant']->id,
                    'name' => $approval['tenant']->name,
                    'slug' => $approval['tenant']->slug,
                    'hostname' => $approval['hostname_label'],
                ],
                'login' => [
                    'email' => $approval['admin']['email'],
                ],
            ];
        }

        return $this->paymentSubmittedResponse($planRequest->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleExistingRequest(PlanRequest $existing, array $data, Plan $plan, bool $isFreePlan): array
    {
        if ($existing->status === 'approved') {
            throw new RuntimeException('البريد الإلكتروني مستخدم بالفعل. يمكنك تسجيل الدخول.');
        }

        if ($existing->status === 'payment_submitted') {
            return [
                'request_id' => $existing->id,
                'status' => 'payment_submitted',
                'auto_provisioned' => false,
                'already_submitted' => true,
                'message' => 'طلبك قيد المراجعة بالفعل. يمكنك متابعة حالة الطلب.',
            ];
        }

        if ($isFreePlan) {
            throw new RuntimeException('يوجد طلب سابق لهذا البريد الإلكتروني.');
        }

        if (in_array($existing->status, ['pending', 'rejected'], true)) {
            $this->assertPaidPlanPaymentPayload($data);

            $plainPassword = (string) $data['password'];

            $existing->update([
                'plan_id' => $plan->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($plainPassword),
                'provision_password' => Crypt::encryptString($plainPassword),
                'company_name' => $data['company_name'] ?? null,
                'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
                'status' => 'payment_submitted',
                'admin_notes' => $existing->status === 'rejected' ? null : $existing->admin_notes,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $this->attachPaymentProof($existing, $data);

            return $this->paymentSubmittedResponse($existing->id);
        }

        throw new RuntimeException('لا يمكن معالجة هذا الطلب حالياً.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPaidPlanPaymentPayload(array $data): void
    {
        if (empty($data['payment_gateway_id'])) {
            throw new RuntimeException('Payment gateway is required for paid plans');
        }

        if (trim((string) ($data['payment_reference'] ?? '')) === '') {
            throw new RuntimeException('Payment reference is required for paid plans');
        }

        if (! ($data['payment_proof'] ?? null) instanceof UploadedFile) {
            throw new RuntimeException('Payment proof image is required for paid plans');
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

    /**
     * @return array<string, mixed>
     */
    private function paymentSubmittedResponse(int $requestId): array
    {
        return [
            'request_id' => $requestId,
            'status' => 'payment_submitted',
            'auto_provisioned' => false,
            'message' => 'تم إرسال إثبات الدفع بنجاح. سيتم مراجعة طلبك وتفعيل حسابك بعد التأكد من التحويل.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(PlanRequest $planRequest, string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '' || $planRequest->email !== $normalizedEmail) {
            throw new RuntimeException('Invalid request lookup');
        }

        $planRequest->loadMissing(['plan', 'paymentGateway']);

        $statusMessage = match ($planRequest->status) {
            'payment_submitted' => 'طلبك قيد المراجعة. سيتم تفعيل حسابك بعد تأكيد الإدارة لاستلام التحويل.',
            'approved' => 'تمت الموافقة على طلبك. يمكنك تسجيل الدخول الآن.',
            'rejected' => 'تم رفض الطلب. تواصل مع الدعم إذا كنت بحاجة للمساعدة.',
            default => 'طلبك قيد المعالجة.',
        };

        return [
            'request_id' => $planRequest->id,
            'status' => $planRequest->status,
            'message' => $statusMessage,
            'payment_submitted_at' => $planRequest->payment_submitted_at?->toISOString(),
            'approved_at' => $planRequest->approved_at?->toISOString(),
            'plan' => $planRequest->plan ? [
                'id' => $planRequest->plan->id,
                'title' => $planRequest->plan->name,
                'price' => number_format((float) $planRequest->plan->price, 2, '.', ''),
            ] : null,
            'payment_gateway' => $planRequest->paymentGateway ? [
                'id' => $planRequest->paymentGateway->id,
                'name' => $planRequest->paymentGateway->name,
            ] : null,
        ];
    }

    public function reject(PlanRequest $planRequest, ?string $adminNotes = null, ?int $rejectedBy = null): PlanRequest
    {
        if ($planRequest->status === 'approved') {
            throw new RuntimeException('Approved requests cannot be rejected');
        }

        $planRequest->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes ?? $planRequest->admin_notes,
            'approved_at' => CarbonImmutable::now(),
            'approved_by' => $rejectedBy,
            'provision_password' => null,
        ]);

        return $planRequest->refresh()->load(['plan', 'paymentGateway', 'tenant']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|PlanRequest
     */
    public function updateStatus(PlanRequest $planRequest, array $data, ?int $actorId = null): array|PlanRequest
    {
        $status = trim((string) ($data['status'] ?? ''));
        $adminNotes = $data['admin_notes'] ?? null;

        if ($status === 'approved') {
            return $this->planRequestApprovalService->approve($planRequest, $actorId);
        }

        if ($status === 'rejected') {
            return $this->reject($planRequest, is_string($adminNotes) ? $adminNotes : null, $actorId);
        }

        $planRequest->update([
            'admin_notes' => $adminNotes ?? $planRequest->admin_notes,
        ]);

        return $planRequest->refresh()->load(['plan', 'paymentGateway', 'tenant']);
    }
}
