<?php

namespace App\Services\Tenant;

use App\Enums\DressStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Support\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $previousPeriod = ReportDateRange::previous($period);
        $branchId = $filters['branch_id'] ?? null;

        $invoiceQuery = $this->invoiceScope($branchId);
        $periodInvoices = (clone $invoiceQuery)
            ->whereDate('created_at', '>=', $period['from'])
            ->whereDate('created_at', '<=', $period['to']);

        $previousInvoices = (clone $invoiceQuery)
            ->whereDate('created_at', '>=', $previousPeriod['from'])
            ->whereDate('created_at', '<=', $previousPeriod['to']);

        $orderCount = (clone $periodInvoices)->count();
        $totalRevenue = round((float) (clone $periodInvoices)->sum('total'), 2);
        $averageOrderValue = $orderCount > 0 ? round($totalRevenue / $orderCount, 2) : 0.0;

        $previousRevenue = round((float) (clone $previousInvoices)->sum('total'), 2);
        $revenueGrowth = $this->growthRate($totalRevenue, $previousRevenue);

        $paymentQuery = InvoicePayment::query()
            ->where('status', InvoicePayment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $period['from'])
            ->whereDate('paid_at', '<=', $period['to']);

        if ($branchId) {
            $paymentQuery->whereHas('invoice', fn (Builder $query) => $query->where('branch_id', $branchId));
        }

        $totalPayments = round((float) (clone $paymentQuery)->sum('amount'), 2);
        $paymentCount = (clone $paymentQuery)->count();

        $expenseQuery = Expense::query()
            ->where('status', Expense::STATUS_PAID)
            ->whereDate('expense_date', '>=', $period['from'])
            ->whereDate('expense_date', '<=', $period['to']);

        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }

        $totalExpenses = round((float) (clone $expenseQuery)->sum('amount'), 2);
        $profit = round($totalPayments - $totalExpenses, 2);
        $profitMargin = $totalPayments > 0 ? round(($profit / $totalPayments) * 100, 1) : 0.0;

        $totalClients = Customer::query()->count();
        $newClients = Customer::query()
            ->whereDate('created_at', '>=', $period['from'])
            ->whereDate('created_at', '<=', $period['to'])
            ->count();
        $previousNewClients = Customer::query()
            ->whereDate('created_at', '>=', $previousPeriod['from'])
            ->whereDate('created_at', '<=', $previousPeriod['to'])
            ->count();
        $clientGrowthRate = $this->growthRate((float) $newClients, (float) $previousNewClients);

        $dressQuery = Dress::query();
        if ($branchId) {
            $dressQuery->where('branch_id', $branchId);
        }

        $totalItems = (clone $dressQuery)->count();
        $availableItems = (clone $dressQuery)->where('status', DressStatus::AVAILABLE->value)->count();
        $rentedItems = (clone $dressQuery)->where('status', DressStatus::RENTED->value)->count();
        $utilizationRate = $totalItems > 0 ? round((($rentedItems + (clone $dressQuery)->where('status', DressStatus::SOLD->value)->count()) / $totalItems) * 100, 1) : 0.0;

        $cashboxBalances = Cashbox::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'current_balance'])
            ->map(fn (Cashbox $cashbox): array => [
                'cashbox_id' => $cashbox->id,
                'name' => $cashbox->name,
                'balance' => round((float) $cashbox->current_balance, 2),
            ])
            ->values()
            ->all();

        $totalCashboxBalance = round(array_sum(array_column($cashboxBalances, 'balance')), 2);

        $salesByStatus = $this->salesByStatus($periodInvoices);
        $recentOrders = $this->recentOrders($invoiceQuery);
        $growthRates = $this->typeGrowthRates($invoiceQuery, $period, $previousPeriod);

        return [
            'kpis' => [
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => (string) $totalRevenue, 'trend' => $this->trendLabel($revenueGrowth)],
                ['key' => 'orders', 'label' => 'Orders', 'value' => (string) $orderCount, 'trend' => $this->trendLabel($this->growthRate((float) $orderCount, (float) (clone $previousInvoices)->count()))],
                ['key' => 'customers', 'label' => 'Customers', 'value' => (string) $totalClients, 'trend' => $this->trendLabel($clientGrowthRate)],
                ['key' => 'payments', 'label' => 'Payments', 'value' => (string) $paymentCount, 'trend' => '—'],
            ],
            'cards' => [
                ['title' => 'Sales overview', 'value' => $orderCount > 0 ? 'Active' : 'Quiet', 'note' => 'Invoices in selected period'],
                ['title' => 'Inventory health', 'value' => $totalItems > 0 ? "{$utilizationRate}%" : '—', 'note' => 'Utilization rate'],
                ['title' => 'Payments summary', 'value' => (string) $paymentCount, 'note' => 'Collected payments'],
            ],
            'kpiData' => [
                'orderCount' => $orderCount,
                'totalRevenue' => $totalRevenue,
                'averageOrderValue' => $averageOrderValue,
                'activeClients' => $totalClients,
                'totalClients' => $totalClients,
                'newClients' => $newClients,
                'clientGrowthRate' => $clientGrowthRate,
                'availableItems' => $availableItems,
                'totalItems' => $totalItems,
                'outOfBranch' => max(0, $totalItems - $availableItems - $rentedItems),
                'utilizationRate' => $utilizationRate,
                'totalPayments' => $totalPayments,
                'paymentCount' => $paymentCount,
                'totalActivities' => $orderCount + $paymentCount,
            ],
            'financialData' => [
                'totalIncome' => $totalPayments,
                'totalExpenses' => $totalExpenses,
                'profit' => $profit,
                'profitMargin' => $profitMargin,
                'cashboxBalances' => $cashboxBalances,
                'totalCashboxBalance' => $totalCashboxBalance,
            ],
            'growthRates' => $growthRates,
            'salesByStatus' => $salesByStatus,
            'recentOrders' => $recentOrders,
            'period' => $period,
        ];
    }

    private function invoiceScope(mixed $branchId): Builder
    {
        return Invoice::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    /**
     * @return array<string, int>
     */
    private function salesByStatus(Builder $query): array
    {
        $counts = [
            'draft' => 0,
            'open' => 0,
            'paid' => 0,
            'cancelled' => 0,
        ];

        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($rows as $status => $total) {
            $bucket = match ($status) {
                InvoiceStatus::DRAFT->value => 'draft',
                InvoiceStatus::PAID->value => 'paid',
                InvoiceStatus::CANCELLED->value, InvoiceStatus::RETURNED->value => 'cancelled',
                default => 'open',
            };
            $counts[$bucket] += (int) $total;
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(Builder $query): array
    {
        return (clone $query)
            ->with('customer:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'invoice_number', 'customer_id', 'type', 'status', 'total', 'created_at'])
            ->map(function (Invoice $invoice): array {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name ?? '—',
                    'type' => $this->mapInvoiceType($invoice->type),
                    'status' => $this->mapInvoiceStatus($invoice->status),
                    'total' => round((float) $invoice->total, 2),
                    'issued_on' => $invoice->created_at?->toDateString() ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{from: string, to: string}  $period
     * @param  array{from: string, to: string}  $previousPeriod
     * @return array<string, float>
     */
    private function typeGrowthRates(Builder $query, array $period, array $previousPeriod): array
    {
        $current = $this->revenueByType($query, $period);
        $previous = $this->revenueByType($query, $previousPeriod);

        return [
            'revenue' => $this->growthRate(array_sum($current), array_sum($previous)),
            'sales' => $this->growthRate($current['sale'], $previous['sale']),
            'rental' => $this->growthRate($current['rent'], $previous['rent']),
            'tailoring' => $this->growthRate($current['tailoring'], $previous['tailoring']),
        ];
    }

    /**
     * @param  array{from: string, to: string}  $range
     * @return array{rent: float, sale: float, tailoring: float}
     */
    private function revenueByType(Builder $query, array $range): array
    {
        $rows = (clone $query)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to'])
            ->selectRaw('type, COALESCE(SUM(total),0) as revenue')
            ->groupBy('type')
            ->pluck('revenue', 'type');

        return [
            'rent' => round((float) ($rows[InvoiceType::RENT->value] ?? 0), 2),
            'sale' => round((float) ($rows[InvoiceType::SELL->value] ?? 0), 2),
            'tailoring' => round((float) ($rows[InvoiceType::TAILORING->value] ?? 0), 2),
        ];
    }

    private function mapInvoiceType(?string $type): string
    {
        return match ($type) {
            InvoiceType::SELL->value => 'sale',
            InvoiceType::TAILORING->value => 'tailoring',
            default => 'rent',
        };
    }

    private function mapInvoiceStatus(?string $status): string
    {
        return match ($status) {
            InvoiceStatus::DRAFT->value => 'draft',
            InvoiceStatus::PAID->value => 'paid',
            InvoiceStatus::CANCELLED->value => 'cancelled',
            default => 'open',
        };
    }

    private function growthRate(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function trendLabel(float $rate): string
    {
        $prefix = $rate >= 0 ? '+' : '';

        return "{$prefix}{$rate}%";
    }
}
