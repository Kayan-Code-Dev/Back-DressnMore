<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use App\Models\Central\Tenant;
use App\Support\PlanCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class SubscriptionPaymentService
{
    public function __construct(
        private readonly PlanRequestApprovalService $approvalService,
        private readonly PlanRequestPaymentProofService $proofService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with(['tenant', 'plan', 'paymentGateway', 'planRequest'])
            ->latest('id');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(reference) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$needle])
                    ->orWhereHas('tenant', fn (Builder $t) => $t->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$needle]));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findOrFail(int $id): Payment
    {
        return Payment::query()
            ->with(['tenant', 'plan', 'paymentGateway', 'planRequest.plan', 'planRequest.oldPlan'])
            ->findOrFail($id);
    }

    public function markPaid(Payment $payment, ?int $adminId = null): Payment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => CarbonImmutable::now(),
        ]);

        $planRequest = $payment->planRequest;
        if ($planRequest !== null && $planRequest->status === 'payment_submitted') {
            $this->approvalService->approve($planRequest, $adminId);
        }

        return $payment->refresh()->load(['tenant', 'plan', 'paymentGateway', 'planRequest']);
    }

    public function reject(Payment $payment, ?string $notes = null): Payment
    {
        $payment->update([
            'status' => 'failed',
            'notes' => trim(($payment->notes ?? '').' '.($notes ?? '')),
        ]);

        if ($payment->planRequest !== null && $payment->planRequest->status === 'payment_submitted') {
            $payment->planRequest->update(['status' => 'rejected']);
        }

        return $payment->refresh();
    }

    public function refund(Payment $payment, ?string $notes = null): Payment
    {
        if ($payment->status !== 'paid') {
            throw new RuntimeException('Only paid payments can be refunded');
        }

        $payment->update([
            'status' => 'refunded',
            'notes' => trim(($payment->notes ?? '').' '.($notes ?? '')),
        ]);

        return $payment->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Payment $payment): array
    {
        $tenant = $payment->relationLoaded('tenant') ? $payment->tenant : null;
        $plan = $payment->relationLoaded('plan') ? $payment->plan : null;
        $gateway = $payment->relationLoaded('paymentGateway') ? $payment->paymentGateway : null;
        $currency = PlanCurrency::normalize($payment->currency ?? $plan?->currency ?? 'EGP');

        return [
            'id' => $payment->id,
            'tenant_id' => $payment->tenant_id,
            'plan_id' => $payment->plan_id,
            'plan_request_id' => $payment->plan_request_id,
            'payment_gateway_id' => $payment->payment_gateway_id,
            'purpose' => $payment->purpose,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $currency,
            'currency_symbol' => PlanCurrency::symbol($currency),
            'method' => $payment->method,
            'reference' => $payment->reference,
            'proof_url' => $this->proofService->url($payment->proof_path),
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'notes' => $payment->notes,
            'created_at' => $payment->created_at?->toDateTimeString(),
            'updated_at' => $payment->updated_at?->toDateTimeString(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ] : null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->name,
                'slug' => $plan->slug,
            ] : null,
            'payment_gateway' => $gateway ? [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'type' => $gateway->type,
            ] : null,
            'order_reference' => $payment->plan_request_id ? 'ORD-'.$payment->plan_request_id : null,
        ];
    }
}
