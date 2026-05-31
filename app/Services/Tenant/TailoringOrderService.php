<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Support\Tenant\TailoringOrderPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TailoringOrderService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)->latest('id')->paginate($perPage)->withQueryString();
    }

    public function findOrFail(int $invoiceId): Invoice
    {
        return $this->baseQuery([])->findOrFail($invoiceId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int|array<string, int>>
     */
    public function stats(array $filters = []): array
    {
        $invoices = $this->baseQuery($filters)->get();
        $active = 0;
        $overdue = 0;
        $ready = 0;
        $completed = 0;
        $inProgress = 0;
        $vipCount = 0;
        $revenue = 0.0;
        $collected = 0.0;
        $remaining = 0.0;
        $unpaidCount = 0;
        /** @var array<string, int> $stageDistribution */
        $stageDistribution = [];

        foreach ($invoices as $invoice) {
            $status = TailoringOrderPresenter::mapStatus($invoice);
            $stage = TailoringOrderPresenter::mapStage($invoice);
            $priority = TailoringOrderPresenter::mapPriority($invoice, $status);
            $paymentStatus = TailoringOrderPresenter::mapPaymentStatus($invoice);

            if ($status === 'active') {
                $active++;
            }
            if ($status === 'overdue') {
                $overdue++;
            }
            if ($status === 'completed') {
                $completed++;
            }
            if ($stage === 'ready_for_delivery') {
                $ready++;
            }
            if ($status === 'active' && ! in_array($stage, ['ready_for_delivery', 'delivered'], true)) {
                $inProgress++;
            }
            if ($priority === 'VIP') {
                $vipCount++;
            }
            if ($paymentStatus === 'unpaid') {
                $unpaidCount++;
            }

            $stageDistribution[$stage] = ($stageDistribution[$stage] ?? 0) + 1;

            if ($invoice->status !== Invoice::STATUS_CANCELLED) {
                $revenue += (float) $invoice->total;
                $collected += (float) $invoice->paid_amount;
                $remaining += (float) $invoice->remaining_amount;
            }
        }

        return [
            'total' => $invoices->where('status', '!=', Invoice::STATUS_CANCELLED)->count(),
            'active' => $active,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'ready' => $ready,
            'completed' => $completed,
            'vip_count' => $vipCount,
            'revenue' => round($revenue, 2),
            'collected' => round($collected, 2),
            'remaining' => round($remaining, 2),
            'unpaid_count' => $unpaidCount,
            'stage_distribution' => $stageDistribution,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $measurements
     */
    public function updateMeasurements(Invoice $invoice, array $measurements): Invoice
    {
        $invoice->tailoring_notes = TailoringOrderPresenter::encodeMeasurements($measurements);
        $invoice->save();

        return $invoice->refresh()->load(['customer', 'items.dress', 'payments']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateDeliveries(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->whereIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED]);

        return $query
            ->latest('delivery_date')
            ->paginate($perPage)
            ->through(function (Invoice $invoice): array {
                $item = $invoice->items->first();

                return [
                    'id' => $invoice->id,
                    'order_id' => $invoice->id,
                    'client_name' => $invoice->customer?->name ?? '',
                    'fabric_name' => $item?->dress?->displayName() ?? ($item?->description ?? ''),
                    'scheduled_date' => $invoice->delivery_date?->toDateString()
                        ?? $invoice->tailoring_due_date?->toDateString()
                        ?? '',
                    'status' => $invoice->status === Invoice::STATUS_DELIVERED ? 'delivered' : 'pending',
                    'employee_name' => '',
                ];
            })
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['customer', 'items.dress', 'payments', 'branch', 'createdBy'])
            ->where('type', Invoice::TYPE_TAILORING);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(invoice_number) LIKE ?', [$needle])
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where(function (Builder $builder) use ($status): void {
                match ($status) {
                    'cancelled' => $builder->where('status', Invoice::STATUS_CANCELLED),
                    'completed' => $builder->whereIn('status', [
                        Invoice::STATUS_PAID,
                        Invoice::STATUS_DELIVERED,
                        Invoice::STATUS_RETURNED,
                    ]),
                    'overdue' => $builder
                        ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED])
                        ->whereDate('tailoring_due_date', '<', Carbon::today()),
                    'active' => $builder->whereNotIn('status', [
                        Invoice::STATUS_CANCELLED,
                        Invoice::STATUS_PAID,
                        Invoice::STATUS_DELIVERED,
                        Invoice::STATUS_RETURNED,
                    ]),
                    default => null,
                };
            });
        }

        return $query;
    }
}
