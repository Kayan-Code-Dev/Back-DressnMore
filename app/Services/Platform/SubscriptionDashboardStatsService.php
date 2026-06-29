<?php

namespace App\Services\Platform;

use App\Models\Central\Payment;
use App\Models\Central\PlanRequest;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;

class SubscriptionDashboardStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $today = CarbonImmutable::today();

        $totalSubscriptions = Tenant::query()->whereNotNull('plan_id')->count();
        $activeSubscriptions = Tenant::query()
            ->whereNotNull('plan_id')
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($today): void {
                $q->whereNull('subscription_ends_at')
                    ->orWhereDate('subscription_ends_at', '>=', $today);
            })
            ->count();

        $expiredSubscriptions = Tenant::query()
            ->whereNotNull('plan_id')
            ->whereNull('cancelled_at')
            ->whereNotNull('subscription_ends_at')
            ->whereDate('subscription_ends_at', '<', $today)
            ->count();

        $cancelledSubscriptions = Tenant::query()
            ->whereNotNull('cancelled_at')
            ->count();

        $pendingPlanRequests = PlanRequest::query()
            ->whereIn('status', ['pending', 'payment_submitted'])
            ->count();

        $totalRevenue = (float) Payment::query()->where('status', 'paid')->sum('amount');
        $pendingPayments = Payment::query()->where('status', 'pending')->count();
        $failedPayments = Payment::query()->whereIn('status', ['failed', 'cancelled'])->count();
        $refundedPayments = Payment::query()->where('status', 'refunded')->count();

        return [
            'total_subscriptions' => $totalSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => $expiredSubscriptions,
            'cancelled_subscriptions' => $cancelledSubscriptions,
            'pending_plan_requests' => $pendingPlanRequests,
            'total_subscription_revenue' => number_format($totalRevenue, 2, '.', ''),
            'pending_payments' => $pendingPayments,
            'failed_payments' => $failedPayments,
            'refunded_payments' => $refundedPayments,
        ];
    }
}
