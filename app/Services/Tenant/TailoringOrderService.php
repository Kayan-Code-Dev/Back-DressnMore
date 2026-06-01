<?php

namespace App\Services\Tenant;

use App\Enums\TailoringPriority;
use App\Enums\TailoringProductionStage;
use App\Enums\TailoringProductionStatus;
use App\Models\Tenant\Invoice;
use App\Support\Tenant\TailoringOrderPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * @return Collection<int, Invoice>
     */
    public function listForBoard(array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->whereNotIn('production_stage', [TailoringProductionStage::DELIVERED->value])
            ->latest('id')
            ->get();
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
        $urgentCount = 0;
        $revenue = 0.0;
        $collected = 0.0;
        $remaining = 0.0;
        $unpaidCount = 0;
        /** @var array<string, int> $stageDistribution */
        $stageDistribution = [];

        foreach ($invoices as $invoice) {
            $status = TailoringOrderPresenter::mapStatus($invoice);
            $stage = TailoringOrderPresenter::resolveStage($invoice);
            $priority = TailoringOrderPresenter::resolvePriority($invoice, $status);
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
            if ($stage === TailoringProductionStage::READY_FOR_DELIVERY->value) {
                $ready++;
            }
            if ($status === 'active' && ! in_array($stage, [
                TailoringProductionStage::READY_FOR_DELIVERY->value,
                TailoringProductionStage::DELIVERED->value,
            ], true)) {
                $inProgress++;
            }
            if (in_array($priority, [TailoringPriority::URGENT->value, TailoringPriority::HIGH->value], true)) {
                $urgentCount++;
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
            'urgent_count' => $urgentCount,
            'vip_count' => $urgentCount,
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
    public function updateMeasurements(Invoice $invoice, array $measurements, ?int $actorId = null): Invoice
    {
        $invoice->tailoring_measurements = TailoringOrderPresenter::normalizeMeasurements($measurements);
        $invoice->save();

        if ($invoice->production_stage === TailoringProductionStage::NEW_ORDER->value) {
            app(TailoringProductionService::class)->changeStage($invoice, [
                'to_stage' => TailoringProductionStage::MEASUREMENTS_TAKEN->value,
                'to_status' => TailoringProductionStatus::IN_PROGRESS->value,
                'notes' => 'تم تحديث المقاسات',
            ], $actorId, true);
        }

        return $invoice->refresh()->load(['customer', 'items.dress', 'payments', 'tailoringStageHistories.changedByUser']);
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
                    'employee_name' => $invoice->createdBy?->name ?? '',
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
            ->with(['customer', 'items.dress', 'payments', 'branch', 'createdBy', 'assignedTailor'])
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
                    'cancelled' => $builder->where(function (Builder $inner): void {
                        $inner->where('status', Invoice::STATUS_CANCELLED)
                            ->orWhere('production_stage', TailoringProductionStage::CANCELLED->value);
                    }),
                    'completed' => $builder->where(function (Builder $inner): void {
                        $inner->whereIn('status', [
                            Invoice::STATUS_PAID,
                            Invoice::STATUS_DELIVERED,
                            Invoice::STATUS_RETURNED,
                        ])->orWhere('production_stage', TailoringProductionStage::DELIVERED->value);
                    }),
                    'overdue' => $builder
                        ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED])
                        ->whereDate('tailoring_due_date', '<', Carbon::today()),
                    'active' => $builder->whereNotIn('status', [
                        Invoice::STATUS_CANCELLED,
                        Invoice::STATUS_PAID,
                        Invoice::STATUS_DELIVERED,
                        Invoice::STATUS_RETURNED,
                    ])->where('production_stage', '!=', TailoringProductionStage::CANCELLED->value),
                    default => null,
                };
            });
        }

        $productionStage = trim((string) ($filters['production_stage'] ?? ($filters['stage'] ?? '')));
        if ($productionStage !== '') {
            $query->where('production_stage', $productionStage);
        }

        $productionStatus = trim((string) ($filters['production_status'] ?? ''));
        if ($productionStatus !== '') {
            $query->where('production_status', $productionStatus);
        }

        $priority = trim((string) ($filters['priority'] ?? ''));
        if ($priority !== '') {
            $query->where('priority', $priority);
        }

        if (isset($filters['assigned_tailor_id']) && $filters['assigned_tailor_id'] !== '') {
            $query->where('assigned_tailor_id', (int) $filters['assigned_tailor_id']);
        }

        if (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (isset($filters['customer_id']) && $filters['customer_id'] !== '') {
            $query->where('customer_id', (int) $filters['customer_id']);
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
}
