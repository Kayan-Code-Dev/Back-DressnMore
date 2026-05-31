<?php

namespace App\Services\Tenant;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Support\ReportDateRange;
use App\Support\Tenant\RentalOrderPresenter;
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
        $filters['type'] = Invoice::TYPE_SELL;

        return $this->invoiceService->paginate($filters, $perPage)
            ->through(fn (Invoice $invoice): array => $this->presentSaleInvoice($invoice));
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
            'discount' => $data['discount'] ?? 0,
            'items' => $data['items'] ?? [],
            'initial_payment' => $data['initial_payment'] ?? null,
        ], $actorId);
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
