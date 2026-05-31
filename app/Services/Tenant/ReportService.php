<?php

namespace App\Services\Tenant;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Tenant\Invoice;
use App\Support\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        $sales = $this->salesSummary($filters);
        $tailoring = $this->tailoringSummary($filters);

        return [
            ...$sales,
            ...$tailoring,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function salesSummary(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $query = $this->invoiceScope($filters)
            ->where('type', InvoiceType::SELL->value)
            ->whereDate('created_at', '>=', $period['from'])
            ->whereDate('created_at', '<=', $period['to'])
            ->whereNotIn('status', [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value]);

        $count = (clone $query)->count();
        $total = round((float) (clone $query)->sum('total'), 2);

        return [
            'total_sales' => $total,
            'invoices_count' => $count,
            'average_invoice_value' => $count > 0 ? round($total / $count, 2) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function tailoringSummary(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $query = $this->invoiceScope($filters)
            ->where('type', InvoiceType::TAILORING->value)
            ->whereDate('created_at', '>=', $period['from'])
            ->whereDate('created_at', '<=', $period['to']);

        $totalOrders = (clone $query)->count();
        $readyOrders = (clone $query)->whereIn('status', [
            InvoiceStatus::PAID->value,
            InvoiceStatus::DELIVERED->value,
        ])->count();
        $lateOrders = (clone $query)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value, InvoiceStatus::CANCELLED->value])
            ->whereDate('tailoring_due_date', '<', now()->toDateString())
            ->count();
        $inProgressOrders = max(0, $totalOrders - $readyOrders - (clone $query)->where('status', InvoiceStatus::CANCELLED->value)->count());
        $totalRevenue = round((float) (clone $query)->whereNotIn('status', [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value])->sum('total'), 2);

        return [
            'total_orders' => $totalOrders,
            'ready_orders' => $readyOrders,
            'late_orders' => $lateOrders,
            'in_progress_orders' => $inProgressOrders,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invoiceScope(array $filters): Builder
    {
        $branchId = $filters['branch_id'] ?? null;

        return Invoice::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }
}
