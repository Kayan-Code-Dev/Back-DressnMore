<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;

class SubscriptionPaymentVerificationResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $reference,
        public readonly ?CarbonImmutable $paidAt,
        public readonly ?string $notes = null,
    ) {}
}
