<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\DressStatus;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class BusinessHealthTool implements SafeBusinessTool
{
    public function name(): string { return 'business_health'; }
    public function description(): string { return 'KPIs and trends: revenue growth, utilization, collection rate, overdue rate.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['dashboard.view', 'reports.view']; }
    public function supports(string $intent): bool { return $intent === 'business_health'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $now = now($context->timezone());
        $excluded = [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value];
        $paidSts = [InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value, InvoiceStatus::RETURNED->value];

        $thisMonth = [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()];
        $thisRev = $this->revenueIn($context, $excluded, $thisMonth[0], $thisMonth[1]);
        $thisPaid = $this->revenueIn($context, $paidSts, $thisMonth[0], $thisMonth[1]);
        $lastMonthFrom = $now->clone()->subMonth()->startOfMonth(); $lastMonthTo = $now->clone()->subMonth()->endOfMonth();
        $lastRev = $this->revenueIn($context, $excluded, $lastMonthFrom, $lastMonthTo);
        $yoyFrom = $now->clone()->subYear()->startOfMonth(); $yoyTo = $now->clone()->subYear()->endOfMonth();
        $yoyRev = $this->revenueIn($context, $excluded, $yoyFrom, $yoyTo);

        $collectionRate = $thisRev['total'] > 0 ? round(($thisPaid['total'] / $thisRev['total']) * 100, 1) : 0;

        $today = $now->toDateString();
        $activeStatuses = [InvoiceStatus::CONFIRMED->value, InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value];
        $activeRentals = Invoice::query()->where('type', 'rent')->whereIn('status', $activeStatuses)->whereDate('return_date', '>=', $today);
        $context->applyBranchScope($activeRentals); $activeCount = $activeRentals->count();

        $overdue = Invoice::query()->where('type', 'rent')->whereNotIn('status', [InvoiceStatus::RETURNED->value, InvoiceStatus::CANCELLED->value])->whereDate('return_date', '<', $today);
        $context->applyBranchScope($overdue); $overdueCount = $overdue->count();
        $totalActive = $activeCount + $overdueCount;
        $overdueRate = $totalActive > 0 ? round(($overdueCount / $totalActive) * 100, 1) : 0;

        $inv = Dress::query(); $context->applyBranchScope($inv);
        $invR = $inv->select([DB::raw("SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented"), DB::raw('COUNT(*) as total')])->first();
        $utilization = ((int) ($invR->total ?? 0)) > 0 ? round(((int) ($invR->rented ?? 0) / (int) $invR->total) * 100, 1) : 0;

        $uniqueCust = Customer::query()->whereBetween('created_at', [$thisMonth[0]->toDateTimeString(), $thisMonth[1]->toDateTimeString()]);
        $context->applyBranchScope($uniqueCust); $newCustomers = $uniqueCust->count();

        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['period' => $now->translatedFormat('F Y'), 'currency' => $context->currency(), 'revenue' => ['this_month' => round($thisRev['total'], 2), 'last_month' => round($lastRev['total'], 2), 'same_month_last_year' => round($yoyRev['total'], 2), 'mom_change_percent' => $this->pctChange($lastRev['total'], $thisRev['total']), 'yoy_change_percent' => $this->pctChange($yoyRev['total'], $thisRev['total']), 'per_invoice' => $thisRev['count'] > 0 ? round($thisRev['total'] / $thisRev['count'], 2) : 0], 'collection' => ['rate_percent' => $collectionRate, 'collected' => round($thisPaid['total'], 2), 'outstanding' => round(max(0, $thisRev['total'] - $thisPaid['total']), 2)], 'rentals' => ['active' => $activeCount, 'overdue' => $overdueCount, 'overdue_rate_percent' => $overdueRate], 'inventory' => ['utilization_percent' => $utilization, 'total_dresses' => (int) ($invR->total ?? 0)], 'customers' => ['new_this_month' => $newCustomers]], scope: ['tenant' => $context->tenantSlug(), 'as_of' => $now->toDateTimeString()]);
    }

    private function revenueIn(BusinessToolContext $ctx, array $statuses, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $q = Invoice::query()->whereIn('status', $statuses)->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);
        $ctx->applyBranchScope($q);
        $r = $q->select([DB::raw('COALESCE(SUM(total), 0) as total'), DB::raw('COUNT(*) as cnt')])->first();
        return ['total' => (float) $r->total, 'count' => (int) $r->cnt];
    }

    private function pctChange(float $prev, float $curr): ?float { if ($prev <= 0) return $curr > 0 ? 100.0 : 0.0; return round((($curr - $prev) / $prev) * 100, 1); }
}
