<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\PaymentGateway;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

class MockSubscriptionPaymentVerifier implements SubscriptionPaymentVerifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function verify(
        Tenant $tenant,
        Plan $plan,
        PaymentGateway $gateway,
        array $data,
    ): SubscriptionPaymentVerificationResult {
        $this->assertMockPaymentsAllowed();

        if (! filter_var($data['mock_payment_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Payment confirmation is required');
        }

        $reference = trim((string) ($data['payment_reference'] ?? ''));
        if ($reference === '') {
            $reference = 'MOCK-'.Str::upper(Str::random(10));
        }

        return new SubscriptionPaymentVerificationResult(
            status: Payment::STATUS_PAID,
            reference: $reference,
            paidAt: CarbonImmutable::now(),
            notes: 'Mock payment verification',
        );
    }

    private function assertMockPaymentsAllowed(): void
    {
        $allowed = (bool) config('billing.allow_mock_payments', false);
        $allowedEnvironments = config('billing.mock_payment_environments', []);

        if ($allowed || in_array(app()->environment(), $allowedEnvironments, true)) {
            return;
        }

        throw new RuntimeException('Mock payments are disabled for this environment');
    }
}
