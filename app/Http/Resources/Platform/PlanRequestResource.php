<?php

namespace App\Http\Resources\Platform;

use App\Services\Platform\PlanRequestPaymentProofService;
use App\Support\PlanCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->relationLoaded('plan') ? $this->plan : null;
        $tenant = $this->relationLoaded('tenant') ? $this->tenant : null;
        $sourceTenant = $this->relationLoaded('sourceTenant') ? $this->sourceTenant : null;
        $proofService = app(PlanRequestPaymentProofService::class);

        return [
            'id' => $this->id,
            'request_type' => $this->request_type ?? 'signup',
            'source_tenant_id' => $this->source_tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'plan_id' => $this->plan_id,
            'old_plan_id' => $this->old_plan_id,
            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_reference' => $this->payment_reference,
            'payment_proof_url' => $proofService->url($this->payment_proof_path),
            'payment_submitted_at' => $this->payment_submitted_at?->toISOString(),
            'status' => $this->status,
            'tenant_id' => $tenant?->slug ?? ($this->tenant_id ? (string) $this->tenant_id : null),
            'tenant_ref_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'admin_notes' => $this->admin_notes,
            'approved_at' => $this->approved_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->name,
                'description' => $plan->description,
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'currency' => PlanCurrency::normalize($plan->currency ?? 'EGP'),
                'currency_symbol' => PlanCurrency::symbol($plan->currency ?? 'EGP'),
                'days' => (int) ($plan->duration_days ?? 30),
            ] : null,
            'payment_gateway' => $this->whenLoaded('paymentGateway', fn () => [
                'id' => $this->paymentGateway?->id,
                'name' => $this->paymentGateway?->name,
                'type' => $this->paymentGateway?->type,
            ]),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ] : null,
            'source_tenant' => $sourceTenant ? [
                'id' => $sourceTenant->id,
                'name' => $sourceTenant->name,
                'slug' => $sourceTenant->slug,
                'status' => $sourceTenant->status,
            ] : null,
        ];
    }
}
