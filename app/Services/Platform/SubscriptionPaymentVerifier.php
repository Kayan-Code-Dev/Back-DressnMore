<?php

namespace App\Services\Platform;

use App\Models\Central\PaymentGateway;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;

interface SubscriptionPaymentVerifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function verify(
        Tenant $tenant,
        Plan $plan,
        PaymentGateway $gateway,
        array $data,
    ): SubscriptionPaymentVerificationResult;
}
