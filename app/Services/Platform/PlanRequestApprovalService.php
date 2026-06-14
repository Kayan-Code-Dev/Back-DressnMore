<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PlanRequest;
use App\Models\Central\Subscription;
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

        Payment::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'payment_gateway_id' => $planRequest->payment_gateway_id,
            'purpose' => 'subscription_signup',
            'amount' => $plan->price,
            'method' => $planRequest->paymentGateway?->name ?? ((float) $plan->price > 0 ? 'manual' : 'free'),
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => 'Plan request #'.$planRequest->id,
        ]);

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
