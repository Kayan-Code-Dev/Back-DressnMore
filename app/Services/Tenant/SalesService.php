<?php

namespace App\Services\Tenant;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Support\ReportDateRange;
use App\Support\Tenant\SaleInvoicePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SalesService
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateInvoices(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->saleInvoiceQuery($filters)
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Invoice $invoice): array => SaleInvoicePresenter::fromInvoice($invoice))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function invoiceStats(array $filters = []): array
    {
        $invoices = $this->saleInvoiceQuery($filters)->get();

        $completed = 0;
        $inProgress = 0;
        $revenue = 0.0;
        $collected = 0.0;
        $remaining = 0.0;

        foreach ($invoices as $invoice) {
            $status = SaleInvoicePresenter::mapInvoiceStatus($invoice);
            if ($status === 'completed') {
                $completed++;
            }
            if ($status === 'in_progress') {
                $inProgress++;
            }

            $revenue += (float) $invoice->total;
            $collected += (float) $invoice->paid_amount;
            $remaining += (float) $invoice->remaining_amount;
        }

        return [
            'total' => $invoices->count(),
            'completed' => $completed,
            'in_progress' => $inProgress,
            'revenue' => round($revenue, 2),
            'collected' => round($collected, 2),
            'remaining' => round($remaining, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function saleInvoiceQuery(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['customer', 'branch', 'createdBy', 'items.dress', 'payments'])
            ->where('type', Invoice::TYPE_SELL);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(invoice_number) LIKE ?', [$needle])
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($needle): void {
                        $customerQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(phone) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(COALESCE(national_id, \'\')) LIKE ?', [$needle]);
                    });
            });
        }

        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        if ($paymentStatus !== '') {
            $query->where(function (Builder $builder) use ($paymentStatus): void {
                match ($paymentStatus) {
                    'paid' => $builder
                        ->where('remaining_amount', '<=', 0)
                        ->where('paid_amount', '>', 0),
                    'partially_paid' => $builder
                        ->where('paid_amount', '>', 0)
                        ->where('remaining_amount', '>', 0),
                    'unpaid' => $builder->where('paid_amount', '<=', 0),
                    default => null,
                };
            });
        }

        $invoiceStatus = trim((string) ($filters['invoice_status'] ?? ''));
        if ($invoiceStatus !== '') {
            $query->where(function (Builder $builder) use ($invoiceStatus): void {
                match ($invoiceStatus) {
                    'cancelled' => $builder->where('status', Invoice::STATUS_CANCELLED),
                    'completed' => $builder->whereIn('status', [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED]),
                    'pending' => $builder->where('status', Invoice::STATUS_DRAFT),
                    'in_progress' => $builder->whereIn('status', [
                        Invoice::STATUS_CONFIRMED,
                        Invoice::STATUS_PARTIALLY_PAID,
                        Invoice::STATUS_PAID,
                    ]),
                    default => null,
                };
            });
        }

        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function reportSummary(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $query = $this->saleScope($filters)
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
     * @return list<array{date:string,invoices_count:int,total:float}>
     */
    public function dailySales(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);

        return $this->saleScope($filters)
            ->selectRaw('DATE(created_at) as sale_date, COUNT(*) as invoices_count, SUM(total) as total')
            ->whereDate('created_at', '>=', $period['from'])
            ->whereDate('created_at', '<=', $period['to'])
            ->whereNotIn('status', [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value])
            ->groupBy('sale_date')
            ->orderByDesc('sale_date')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->sale_date,
                'invoices_count' => (int) $row->invoices_count,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{product_name:string,product_code:string,quantity_sold:int,revenue:float}>
     */
    public function productSales(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);

        return InvoiceItem::query()
            ->with('dress')
            ->whereHas('invoice', function (Builder $builder) use ($filters, $period): void {
                $builder->where('type', Invoice::TYPE_SELL)
                    ->whereDate('created_at', '>=', $period['from'])
                    ->whereDate('created_at', '<=', $period['to'])
                    ->whereNotIn('status', [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value])
                    ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId));
            })
            ->get()
            ->groupBy(fn (InvoiceItem $item): string => (string) ($item->dress?->code ?? $item->description ?? 'item-'.$item->id))
            ->map(function ($items, $code): array {
                /** @var InvoiceItem $first */
                $first = $items->first();

                return [
                    'product_name' => $first->dress?->displayName() ?? ($first->description ?? 'Product'),
                    'product_code' => (string) $code,
                    'quantity_sold' => (int) $items->sum('quantity'),
                    'revenue' => round((float) $items->sum('total'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{employee_name:string,invoices_count:int,total_sales:float}>
     */
    public function employeeSales(array $filters): array
    {
        return [[
            'employee_name' => '—',
            'invoices_count' => 0,
            'total_sales' => 0,
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSale(array $data, ?int $actorId = null): Invoice
    {
        return $this->invoiceService->create([
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'customer_id' => $data['customer_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'order_notes' => $data['order_notes'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'items' => $data['items'] ?? [],
            'initial_payment' => $data['initial_payment'] ?? null,
        ], $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCreatedSale(Invoice $invoice): array
    {
        $invoice->refresh()->loadMissing(['customer', 'branch', 'createdBy', 'items.dress', 'payments']);

        return array_merge(SaleInvoicePresenter::fromInvoice($invoice), [
            'paid_amount' => $invoice->paid_amount,
            'remaining_amount' => $invoice->remaining_amount,
            'status' => $invoice->status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSaleInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'branch', 'items.dress', 'payments']);
        $payment = $invoice->payments->first();

        return [
            'id' => $invoice->id,
            'client_name' => $invoice->customer?->name ?? '',
            'employee_name' => '',
            'branch_name' => $invoice->branch?->name ?? '',
            'sale_date' => $invoice->created_at?->toDateString() ?? '',
            'payment_method' => $payment?->method ?? 'cash',
            'subtotal' => (float) $invoice->subtotal,
            'discount' => (float) $invoice->discount,
            'total' => (float) $invoice->total,
            'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'product_code' => $item->dress?->code ?? '',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function saleScope(array $filters): Builder
    {
        return Invoice::query()
            ->where('type', Invoice::TYPE_SELL)
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId));
    }
}
