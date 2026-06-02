<?php

namespace App\Services\Tenant;

use App\Enums\DressStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Support\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    public function __construct(
        private readonly SalesService $salesService,
        private readonly RentalOrderService $rentalOrderService,
        private readonly TailoringOrderService $tailoringOrderService,
        private readonly ExpenseService $expenseService,
        private readonly CustomerService $customerService,
        private readonly AccountingService $accountingService,
        private readonly CashboxService $cashboxService,
        private readonly InvoiceDeliveryListService $deliveryListService,
        private readonly InvoiceReturnListService $returnListService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        return [
            'sales' => $this->salesSummary($filters),
            'tailoring' => $this->tailoringSummary($filters),
            'rental' => $this->rental($filters),
            'expenses' => $this->expenses($filters),
            'customers' => $this->customers($filters),
            'cash' => $this->cash($filters),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function resolve(string $key, array $filters): array
    {
        return match ($key) {
            'overview' => $this->overview($filters),
            'sales' => $this->salesSummary($filters),
            'sales-daily' => $this->salesDaily($filters),
            'sales-products' => $this->salesProducts($filters),
            'sales-employees' => $this->salesEmployees($filters),
            'tailoring' => $this->tailoringSummary($filters),
            'rental' => $this->rental($filters),
            'deliveries' => $this->deliveries($filters),
            'returns' => $this->returns($filters),
            'customers' => $this->customers($filters),
            'inventory' => $this->inventory($filters),
            'expenses' => $this->expenses($filters),
            'cash' => $this->cash($filters),
            'accounting' => $this->accounting($filters),
            'payments' => $this->payments($filters),
            'suppliers' => $this->suppliers($filters),
            default => throw new \InvalidArgumentException("Unknown report [{$key}]"),
        };
    }

    /** @param array<string, mixed> $filters */
    public function salesSummary(array $filters): array
    {
        return $this->salesService->reportSummary($filters);
    }

    /** @param array<string, mixed> $filters */
    public function salesDaily(array $filters): array
    {
        return ['items' => $this->salesService->dailySales($filters), 'period' => ReportDateRange::resolve($filters)];
    }

    /** @param array<string, mixed> $filters */
    public function salesProducts(array $filters): array
    {
        return ['items' => $this->salesService->productSales($filters), 'period' => ReportDateRange::resolve($filters)];
    }

    /** @param array<string, mixed> $filters */
    public function salesEmployees(array $filters): array
    {
        return ['items' => $this->salesService->employeeSales($filters), 'period' => ReportDateRange::resolve($filters)];
    }

    /** @param array<string, mixed> $filters */
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
            'period' => $period,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function rental(array $filters): array
    {
        return [
            ...$this->rentalOrderService->stats($this->withPeriod($filters)),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function deliveries(array $filters): array
    {
        return [
            ...$this->deliveryListService->stats($this->withPeriod($filters)),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function returns(array $filters): array
    {
        return [
            ...$this->returnListService->stats($this->withPeriod($filters)),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function customers(array $filters): array
    {
        return [
            ...$this->customerService->stats(),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function inventory(array $filters): array
    {
        $total = Dress::query()->count();
        $byStatus = Dress::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $available = (int) ($byStatus[DressStatus::AVAILABLE->value] ?? 0);
        $rented = (int) ($byStatus[DressStatus::RENTED->value] ?? 0);
        $sold = (int) ($byStatus[DressStatus::SOLD->value] ?? 0);
        $utilization = $total > 0 ? round((($rented + $sold) / $total) * 100, 2) : 0.0;

        return [
            'total_dresses' => $total,
            'available' => $available,
            'rented' => $rented,
            'sold' => $sold,
            'utilization_percent' => $utilization,
            'by_status' => $byStatus,
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function expenses(array $filters): array
    {
        return [
            ...$this->expenseService->summary($this->withPeriod($filters)),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function cash(array $filters): array
    {
        return [
            ...$this->cashboxService->dailySummary($this->withPeriod($filters)),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function accounting(array $filters): array
    {
        return [
            ...$this->accountingService->summary($filters),
            'period' => ReportDateRange::resolve($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function payments(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $query = InvoicePayment::query()
            ->where('status', InvoicePayment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $period['from'])
            ->whereDate('paid_at', '<=', $period['to']);

        if ($filters['branch_id'] ?? null) {
            $query->whereHas('invoice', fn (Builder $builder) => $builder->where('branch_id', $filters['branch_id']));
        }

        $total = round((float) (clone $query)->sum('amount'), 2);
        $count = (clone $query)->count();
        $byMethod = (clone $query)
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount),0) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) $row->method,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        return [
            'total_amount' => $total,
            'payments_count' => $count,
            'by_method' => $byMethod,
            'period' => $period,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function suppliers(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $suppliers = Supplier::query()->orderBy('name')->get()->map(function (Supplier $supplier) use ($period): array {
            $ordersQuery = PurchaseOrder::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '>=', $period['from'])
                ->whereDate('created_at', '<=', $period['to']);

            $ordersCount = (clone $ordersQuery)->count();
            $totalPurchases = round((float) (clone $ordersQuery)->sum('total'), 2);
            $totalPaid = round((float) (clone $ordersQuery)->sum('paid_amount'), 2);

            return [
                'name' => $supplier->name,
                'orders_count' => $ordersCount,
                'total_purchases' => $totalPurchases,
                'total_paid' => $totalPaid,
                'balance' => round($totalPurchases - $totalPaid, 2),
            ];
        })->values()->all();

        return [
            'suppliers' => $suppliers,
            'period' => $period,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withPeriod(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);

        return array_merge($filters, [
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ]);
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
