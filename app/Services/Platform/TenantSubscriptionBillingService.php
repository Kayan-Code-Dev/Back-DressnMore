<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\PaymentGateway;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Support\PlanFeatureCatalog;
use App\Support\TenantSubscriptionPresenter;
use Carbon\CarbonImmutable;
use RuntimeException;

class TenantSubscriptionBillingService
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly TenantSubscriptionPresenter $subscriptionPresenter,
        private readonly SubscriptionPaymentVerifier $paymentVerifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Tenant $tenant): array
    {
        $tenant->loadMissing(['plan.features']);

        return [
            'subscription' => $this->subscriptionPresenter->forTenant($tenant),
            'tenant' => [
                'id' => (string) $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'available_plans' => $this->availablePlans($tenant),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activePaymentGateways(): array
    {
        return PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (PaymentGateway $gateway): array => $this->presentGateway($gateway))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function renew(Tenant $tenant, array $data): array
    {
        $plan = $tenant->plan;
        if ($plan === null) {
            throw new RuntimeException('Tenant has no active plan');
        }

        if ((float) $plan->price > 0) {
            throw new RuntimeException('Paid plans require upgrade flow');
        }

        $days = (int) ($data['extension_days'] ?? $plan->duration_days ?? 30);
        $tenant = $this->tenantProvisioningService->renew($tenant, ['days' => $days]);

        return $this->subscriptionPresenter->forTenant($tenant->refresh()->load(['plan.features']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upgrade(Tenant $tenant, array $data): array
    {
        $plan = Plan::query()
            ->with('features')
            ->where('slug', (string) ($data['plan_code'] ?? ''))
            ->where('status', 'active')
            ->firstOrFail();

        $isPaid = (float) $plan->price > 0;

        if ($isPaid) {
            $gateway = PaymentGateway::query()
                ->where('id', (int) $data['payment_gateway_id'])
                ->where('is_active', true)
                ->firstOrFail();
            $verification = $this->paymentVerifier->verify($tenant, $plan, $gateway, $data);

            $payment = Payment::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'payment_gateway_id' => $gateway->id,
                'purpose' => 'subscription_upgrade',
                'amount' => $plan->price,
                'method' => $gateway->type,
                'reference' => $verification->reference,
                'status' => $verification->status,
                'paid_at' => $verification->paidAt,
                'notes' => $verification->notes,
            ]);

            if ($payment->status !== Payment::STATUS_PAID) {
                throw new RuntimeException('Payment was not completed');
            }

            $gateway->increment('usage_count');
        }

        $tenant->plan_id = $plan->id;
        $tenant->subscription_starts_at = CarbonImmutable::now();
        $tenant->subscription_ends_at = CarbonImmutable::now()->addDays((int) ($plan->duration_days ?? 30));
        $tenant->status = 'active';
        $tenant->save();

        return $this->subscriptionPresenter->forTenant($tenant->refresh()->load(['plan.features']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availablePlans(Tenant $tenant): array
    {
        return Plan::query()
            ->with('features')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): array => $this->presentPlanOption($plan, $tenant))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPlanOption(Plan $plan, Tenant $tenant): array
    {
        $isPaid = (float) $plan->price > 0;

        return [
            'code' => $plan->slug,
            'name' => $plan->name,
            'account_type' => $isPaid ? 'paid' : 'free',
            'price' => (float) $plan->price,
            'currency' => 'ج.م',
            'billing_period_days' => $plan->duration_days,
            'description' => $plan->description ?? '',
            'features' => $this->planFeatureLabels($plan),
            'is_current' => (int) $tenant->plan_id === (int) $plan->id,
        ];
    }

    /**
     * @return list<string>
     */
    private function planFeatureLabels(Plan $plan): array
    {
        $labels = [];
        foreach ($plan->features as $feature) {
            if (! str_ends_with($feature->feature_key, '.enabled')) {
                continue;
            }
            if (! PlanFeatureCatalog::isEnabledValue($feature->feature_value)) {
                continue;
            }

            foreach (PlanFeatureCatalog::definitions() as $definition) {
                if ($definition['key'] === $feature->feature_key) {
                    $labels[] = $definition['label'];
                    break;
                }
            }
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGateway(PaymentGateway $gateway): array
    {
        return [
            'id' => (string) $gateway->id,
            'name' => $gateway->name,
            'type' => $gateway->type,
            'account_holder' => $gateway->account_holder,
            'account_number' => $gateway->account_number,
            'bank_name' => $gateway->bank_name,
            'iban' => $gateway->iban,
            'instructions' => $gateway->instructions,
            'is_active' => $gateway->is_active,
            'display_order' => $gateway->display_order,
        ];
    }
}
