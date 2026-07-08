<?php

namespace App\Services\Platform;

use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;
use RuntimeException;

class TenantSubscriptionCancellationService
{
    /**
     * @return array<string, mixed>
     */
    public function cancel(Tenant $tenant, ?string $reason = null): array
    {
        if ($tenant->cancelled_at !== null) {
            throw new RuntimeException('الاشتراك ملغي بالفعل');
        }

        $tenant->update([
            'cancelled_at' => CarbonImmutable::now(),
            'cancellation_reason' => $reason,
        ]);

        return [
            'cancelled_at' => $tenant->cancelled_at?->toISOString(),
            'cancellation_reason' => $tenant->cancellation_reason,
            'message' => 'تم إلغاء الاشتراك. يمكنك اختيار باقة جديدة في أي وقت.',
        ];
    }
}
