<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PlanRequest;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

class PlanRequestApprovalService
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
    ) {}

    /**
     * @return array{
     *     tenant: \App\Models\Central\Tenant,
     *     subscription: Subscription,
     *     request: PlanRequest,
     *     admin: array{email: string, password: string, warning?: string},
     *     hostname_label: string
     * }
     */
    public function approve(PlanRequest $planRequest, ?int $approvedBy = null): array
    {
        $planRequest->loadMissing(['plan', 'paymentGateway']);

        if ($planRequest->status === 'approved') {
            throw new RuntimeException('Request already approved');
        }

        if ($planRequest->status === 'rejected') {
            throw new RuntimeException('Rejected requests cannot be approved');
        }

        $plan = $planRequest->plan;
        if ($plan === null) {
            throw new RuntimeException('Plan not found for request');
        }

        if ((float) $plan->price > 0 && $planRequest->payment_proof_path === null) {
            throw new RuntimeException('Cannot approve paid request before payment proof is submitted');
        }

        if ((float) $plan->price > 0 && $planRequest->status !== 'payment_submitted') {
            throw new RuntimeException('Paid request must be in payment_submitted status before approval');
        }

        if (($planRequest->request_type ?? 'signup') !== 'signup') {
            return $this->approveTenantChange($planRequest, $approvedBy);
        }

        $plainPassword = $this->resolveProvisionPassword($planRequest);
        $startsAt = CarbonImmutable::now();
        $endsAt = $startsAt->addDays(max(1, (int) ($plan->duration_days ?? 30)));

        $tenant = $this->tenantProvisioningService->provision([
            'name' => $planRequest->company_name ?: $planRequest->name,
            'slug' => $this->resolveSlugSeed($planRequest),
            'plan_id' => $plan->id,
            'subscription_starts_at' => $startsAt,
            'subscription_ends_at' => $endsAt,
            'metadata' => [
                'admin_email' => $planRequest->email,
                'phone' => $planRequest->phone,
                'source' => 'plan_request',
                'plan_request_id' => $planRequest->id,
            ],
        ]);

        $hostnameLabel = $this->primaryHostname($tenant->slug);
        $this->tenantProvisioningService->addDomain($tenant, $hostnameLabel);

        $credentials = $this->tenantProvisioningService->seedAdmin($tenant, [
            'admin_email' => $planRequest->email,
            'admin_password' => $plainPassword,
            'admin_name' => $planRequest->name,
            'phone' => $planRequest->phone,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->recordPayment($planRequest, $tenant, $plan, 'subscription_signup');

        $planRequest->update([
            'status' => 'approved',
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'provision_password' => null,
        ]);

        if ($planRequest->payment_gateway_id) {
            PaymentGateway::query()
                ->whereKey($planRequest->payment_gateway_id)
                ->increment('usage_count');
        }

        return [
            'tenant' => $tenant->refresh()->load(['plan', 'domains']),
            'subscription' => $subscription,
            'request' => $planRequest->refresh()->load(['plan', 'paymentGateway', 'tenant']),
            'admin' => [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'warning' => 'يرجى حفظ كلمة المرور هذه فوراً لن تُعرض مرة أخرى.',
            ],
            'hostname_label' => $hostnameLabel,
        ];
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     subscription: Subscription,
     *     request: PlanRequest,
     *     hostname_label: string
     * }
     */
    private function approveTenantChange(PlanRequest $planRequest, ?int $approvedBy = null): array
    {
        $plan = $planRequest->plan;
        if ($plan === null) {
            throw new RuntimeException('Plan not found for request');
        }

        $tenantId = $planRequest->source_tenant_id;
        if ($tenantId === null || trim((string) $tenantId) === '') {
            throw new RuntimeException('Change request is missing source tenant');
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $requestType = (string) ($planRequest->request_type ?? 'upgrade');
        $durationDays = max(1, (int) ($plan->duration_days ?? 30));

        if ($requestType === 'renew') {
            $base = $tenant->subscription_ends_at !== null
                ? CarbonImmutable::parse((string) $tenant->subscription_ends_at)
                : CarbonImmutable::now();
            $startsAt = $tenant->subscription_starts_at !== null
                ? CarbonImmutable::parse((string) $tenant->subscription_starts_at)
                : CarbonImmutable::now();
            $endsAt = ($base->lt(CarbonImmutable::now()) ? CarbonImmutable::now() : $base)->addDays($durationDays);
        } else {
            $startsAt = CarbonImmutable::now();
            $endsAt = $startsAt->addDays($durationDays);
        }

        $tenant->update([
            'plan_id' => $plan->id,
            'subscription_starts_at' => $requestType === 'renew' ? $startsAt : $startsAt,
            'subscription_ends_at' => $endsAt,
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->recordPayment($planRequest, $tenant, $plan, 'subscription_'.$requestType);

        $planRequest->update([
            'status' => 'approved',
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'provision_password' => null,
        ]);

        if ($planRequest->payment_gateway_id) {
            PaymentGateway::query()
                ->whereKey($planRequest->payment_gateway_id)
                ->increment('usage_count');
        }

        return [
            'tenant' => $tenant->refresh()->load(['plan', 'domains']),
            'subscription' => $subscription,
            'request' => $planRequest->refresh()->load(['plan', 'paymentGateway', 'tenant', 'sourceTenant', 'oldPlan']),
            'hostname_label' => $this->primaryHostname($tenant->slug),
        ];
    }

    private function recordPayment(PlanRequest $planRequest, Tenant $tenant, \App\Models\Central\Plan $plan, string $purpose): void
    {
        $existing = Payment::query()
            ->where('plan_request_id', $planRequest->id)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'status' => 'paid',
                'paid_at' => now(),
                'amount' => $plan->price,
                'plan_id' => $plan->id,
            ]);

            return;
        }

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'plan_request_id' => $planRequest->id,
            'payment_gateway_id' => $planRequest->payment_gateway_id,
            'purpose' => $purpose,
            'amount' => $plan->price,
            'currency' => \App\Support\PlanCurrency::normalize($plan->currency ?? 'EGP'),
            'method' => $planRequest->paymentGateway?->name ?? 'manual',
            'reference' => $planRequest->payment_reference,
            'proof_path' => $planRequest->payment_proof_path,
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => 'Plan request #'.$planRequest->id,
        ]);
    }

    /**
     * @deprecated use approveTenantChange
     */
    private function approveUpgrade(PlanRequest $planRequest, ?int $approvedBy = null): array
    {
        return $this->approveTenantChange($planRequest, $approvedBy);
    }

    private function resolveProvisionPassword(PlanRequest $planRequest): string
    {
        $encrypted = trim((string) ($planRequest->provision_password ?? ''));
        if ($encrypted !== '') {
            try {
                $password = trim(Crypt::decryptString($encrypted));
                if ($password !== '') {
                    return $password;
                }
            } catch (DecryptException) {
                // Fall through to generated password.
            }
        }

        return Str::random(12);
    }

    private function resolveSlugSeed(PlanRequest $planRequest): string
    {
        if ($planRequest->company_name) {
            return $planRequest->company_name;
        }

        if ($planRequest->name) {
            return $planRequest->name;
        }

        return (string) Str::before($planRequest->email, '@');
    }

    private function primaryHostname(string $slug): string
    {
        $baseDomains = config('tenancy.domain.base_domains', ['dressnmore.it.com']);
        $baseDomain = is_array($baseDomains) && $baseDomains !== []
            ? (string) $baseDomains[0]
            : 'dressnmore.it.com';

        return strtolower($slug.'.'.$baseDomain);
    }
}
