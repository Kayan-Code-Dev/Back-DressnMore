<?php

namespace App\Services\Platform;

use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PlanRequestService
{
    public function __construct(
        private readonly PlanRequestApprovalService $planRequestApprovalService,
    ) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PlanRequest::query()
            ->with(['plan', 'paymentGateway', 'tenant'])
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

        if (! $isFreePlan && empty($data['payment_gateway_id'])) {
            throw new RuntimeException('Payment gateway is required for paid plans');
        }

        $plainPassword = (string) $data['password'];

        $planRequest = PlanRequest::query()->create([
            'plan_id' => $plan->id,
            'name' => $data['name'],
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => $data['phone'],
            'password' => Hash::make($plainPassword),
            'provision_password' => Crypt::encryptString($plainPassword),
            'company_name' => $data['company_name'] ?? null,
            'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
            'status' => 'pending',
        ]);

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

        return [
            'request_id' => $planRequest->id,
            'status' => 'pending',
            'auto_provisioned' => false,
            'message' => 'تم إرسال طلبك بنجاح. سيتم مراجعته وتفعيل حسابك بعد الموافقة.',
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
