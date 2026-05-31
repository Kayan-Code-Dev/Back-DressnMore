<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeliveryWorkflowService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateDeliveries(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateInvoiceItems(
            filters: $filters,
            perPage: $perPage,
            invoiceStatuses: [
                Invoice::STATUS_CONFIRMED,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_PAID,
                Invoice::STATUS_DELIVERED,
            ],
            mapper: fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'order_id' => (string) ($item->invoice?->invoice_number ?? $item->invoice_id),
                'client' => $item->invoice?->customer?->name ?? '',
                'employee' => '',
                'cloth_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'cloth_code' => $item->dress?->code ?? '',
                'delivery_date' => $item->invoice?->delivery_date?->toDateString() ?? '',
                'status' => $item->invoice?->status === Invoice::STATUS_DELIVERED ? 'delivered' : 'ready',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateReturns(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateInvoiceItems(
            filters: $filters,
            perPage: $perPage,
            invoiceStatuses: [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED],
            mapper: fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'order_id' => (string) ($item->invoice?->invoice_number ?? $item->invoice_id),
                'client' => $item->invoice?->customer?->name ?? '',
                'employee' => '',
                'cloth_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'cloth_code' => $item->dress?->code ?? '',
                'return_date' => $item->invoice?->return_date?->toDateString()
                    ?? $item->invoice?->rent_end_date?->toDateString()
                    ?? '',
                'status' => $item->invoice?->status === Invoice::STATUS_RETURNED ? 'returned' : 'requested',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateOverdueReturns(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['customer', 'items.dress'])
            ->where('type', Invoice::TYPE_RENT)
            ->where('status', Invoice::STATUS_DELIVERED)
            ->whereDate('rent_end_date', '<', Carbon::today());

        $this->applySearch($query, $filters);

        return $query
            ->latest('rent_end_date')
            ->paginate($perPage)
            ->through(function (Invoice $invoice): array {
                $item = $invoice->items->first();
                $overdueDays = $invoice->rent_end_date
                    ? max(0, Carbon::parse((string) $invoice->rent_end_date)->diffInDays(Carbon::today(), false))
                    : 0;

                return [
                    'id' => $invoice->id,
                    'customer' => $invoice->customer?->name ?? '',
                    'invoice_number' => $invoice->invoice_number,
                    'item' => $item?->dress?->displayName() ?? ($item?->description ?? ''),
                    'delivery_date' => $invoice->delivery_date?->toDateString() ?? '',
                    'expected_return_date' => $invoice->rent_end_date?->toDateString() ?? '',
                    'overdue_days' => $overdueDays,
                    'amount' => (float) $invoice->remaining_amount,
                    'status' => 'overdue',
                ];
            })
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateDeliverySearch(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $deliveries = $this->collectDeliverySearchRows($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;
        $slice = $deliveries->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            items: $slice,
            total: $deliveries->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function collectDeliverySearchRows(array $filters): Collection
    {
        $rows = collect();

        $deliveryItems = $this->invoiceItemQuery(
            invoiceStatuses: [
                Invoice::STATUS_CONFIRMED,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_PAID,
                Invoice::STATUS_DELIVERED,
            ],
            filters: $filters,
        )->get();

        foreach ($deliveryItems as $item) {
            $invoice = $item->invoice;
            if ($invoice === null) {
                continue;
            }

            $rows->push([
                'id' => $item->id,
                'order_id' => $invoice->id,
                'client_name' => $invoice->customer?->name ?? '',
                'cloth_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'cloth_code' => $item->dress?->code ?? '',
                'type' => 'delivery',
                'scheduled_date' => $invoice->delivery_date?->toDateString() ?? '',
                'status' => $invoice->status === Invoice::STATUS_DELIVERED ? 'done' : 'pending',
                'employee_name' => '',
            ]);

            if ($invoice->status === Invoice::STATUS_DELIVERED) {
                $rows->push([
                    'id' => $item->id * 10000 + 1,
                    'order_id' => $invoice->id,
                    'client_name' => $invoice->customer?->name ?? '',
                    'cloth_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                    'cloth_code' => $item->dress?->code ?? '',
                    'type' => 'return',
                    'scheduled_date' => $invoice->rent_end_date?->toDateString() ?? '',
                    'status' => $invoice->status === Invoice::STATUS_RETURNED
                        ? 'done'
                        : ($invoice->rent_end_date && Carbon::parse((string) $invoice->rent_end_date)->lt(Carbon::today()) ? 'overdue' : 'pending'),
                    'employee_name' => '',
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $invoiceStatuses
     * @param  callable(InvoiceItem): array<string, mixed>  $mapper
     */
    private function paginateInvoiceItems(
        array $filters,
        int $perPage,
        array $invoiceStatuses,
        callable $mapper,
    ): LengthAwarePaginator {
        return $this->invoiceItemQuery($invoiceStatuses, $filters)
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (InvoiceItem $item): array => $mapper($item))
            ->withQueryString();
    }

    /**
     * @param  list<string>  $invoiceStatuses
     * @param  array<string, mixed>  $filters
     * @return Builder<InvoiceItem>
     */
    private function invoiceItemQuery(array $invoiceStatuses, array $filters): Builder
    {
        $query = InvoiceItem::query()
            ->with(['invoice.customer', 'dress'])
            ->whereHas('invoice', function (Builder $invoiceQuery) use ($invoiceStatuses): void {
                $invoiceQuery
                    ->where('type', Invoice::TYPE_RENT)
                    ->whereIn('status', $invoiceStatuses);
            });

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->whereRaw('LOWER(invoice_number) LIKE ?', [$needle]))
                    ->orWhereHas('invoice.customer', fn (Builder $customerQuery) => $customerQuery->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('dress', fn (Builder $dressQuery) => $dressQuery->whereRaw('LOWER(code) LIKE ?', [$needle]));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search === '') {
            return;
        }

        $needle = '%'.mb_strtolower($search).'%';
        $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(invoice_number) LIKE ?', [$needle])
                ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->whereRaw('LOWER(name) LIKE ?', [$needle]));
        });
    }
}
