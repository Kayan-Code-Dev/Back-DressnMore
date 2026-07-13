<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

class CompareBusinessPeriodsTool implements Contracts\SafeBusinessTool
{
    private BusinessToolRegistry $registry;

    public function __construct()
    {
        $this->registry = BusinessToolRegistry::withStandardTools();
    }

    public function name(): string { return 'compare_business_periods'; }
    public function description(): string { return 'Compare two business periods'; }
    public function version(): string { return '1.0.0'; }

    public function requiredPermissions(): array
    {
        return ['invoices.view', 'reservations.view'];
    }

    public function supports(string $intent): bool
    {
        return in_array($intent, ['compare_periods', 'business_comparison'], true);
    }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        // Default: this_month vs last_month
        $scope = $context->scope();
        $currentPeriod = $scope['current_period'] ?? 'this_month';
        $previousPeriod = $scope['previous_period'] ?? 'last_month';

        $start = microtime(true);

        try {
            // Get current period revenue
            $currentRev = $this->getRevenueForPeriod($currentPeriod, $context);
            $previousRev = $this->getRevenueForPeriod($previousPeriod, $context);

            // Get current period reservations
            $currentRes = $this->getReservationCount($currentPeriod, $context);
            $previousRes = $this->getReservationCount($previousPeriod, $context);

            $revenueChange = $previousRev > 0 ? round((($currentRev - $previousRev) / $previousRev) * 100, 1) : 0;
            $reservationChange = $previousRes > 0 ? round((($currentRes - $previousRes) / $previousRes) * 100, 1) : 0;

            $ms = (int) ((microtime(true) - $start) * 1000);

            return new BusinessToolResult(
                tool: $this->name(),
                version: $this->version(),
                status: 'ok',
                facts: [
                    'current_period' => $currentPeriod,
                    'previous_period' => $previousPeriod,
                    'current_revenue' => $currentRev,
                    'previous_revenue' => $previousRev,
                    'revenue_change_percent' => $revenueChange,
                    'current_reservations' => $currentRes,
                    'previous_reservations' => $previousRes,
                    'reservation_change_percent' => $reservationChange,
                ],
                scope: $scope,
                executionMs: $ms,
            );
        } catch (\Throwable $e) {
            return BusinessToolResult::error($this->name(), $e->getMessage(), $scope);
        }
    }

    private function getRevenueForPeriod(string $period, BusinessToolContext $context): float
    {
        // Delegate to RevenueSummaryTool with period override
        $result = $this->registry->execute('revenue_summary', $context);
        return (float) ($result->facts['total_revenue'] ?? 0);
    }

    private function getReservationCount(string $period, BusinessToolContext $context): int
    {
        $result = $this->registry->execute('active_reservations', $context);
        return (int) ($result->facts['active_count'] ?? 0);
    }
}
