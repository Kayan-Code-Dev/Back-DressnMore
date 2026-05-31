<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Support\Tenant\InvoiceDeliveryPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InvoiceDeliveryListService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderByRaw('COALESCE(occasion_datetime, delivery_date, created_at) ASC')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int|array<string, int>>
     */
    public function stats(array $filters = []): array
    {
        $invoices = $this->baseQuery($filters)->get();

        $todayWeddings = 0;
        $waitingDelivery = 0;
        $lateReturns = 0;
        $revenue = 0.0;
        $collected = 0.0;
        $remaining = 0.0;
        $distribution = [
            'waiting' => 0,
            'received' => 0,
            'delivered' => 0,
            'returned' => 0,
            'late' => 0,
        ];

        foreach ($invoices as $invoice) {
            $deliveryStatus = InvoiceDeliveryPresenter::mapDeliveryStatus($invoice);
            if (array_key_exists($deliveryStatus, $distribution)) {
                $distribution[$deliveryStatus]++;
            }

            if ($invoice->occasion_datetime !== null
                && Carbon::parse((string) $invoice->occasion_datetime)->isToday()) {
                $todayWeddings++;
            }

            if ($deliveryStatus === 'waiting') {
                $waitingDelivery++;
            }

            if ($deliveryStatus === 'late') {
                $lateReturns++;
            }

            $revenue += (float) $invoice->total;
            $collected += (float) $invoice->paid_amount;
            $remaining += (float) $invoice->remaining_amount;
        }

        return [
            'total' => $invoices->count(),
            'today_weddings' => $todayWeddings,
            'waiting_delivery' => $waitingDelivery,
            'late_returns' => $lateReturns,
            'revenue' => round($revenue, 2),
            'collected' => round($collected, 2),
            'remaining' => round($remaining, 2),
            'status_distribution' => $distribution,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['customer', 'branch', 'createdBy', 'items.dress', 'payments'])
            ->where('type', Invoice::TYPE_RENT)
            ->where('status', '!=', Invoice::STATUS_CANCELLED);

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
                            ->orWhereRaw('LOWER(COALESCE(national_id, \'\')) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(COALESCE(whatsapp, \'\')) LIKE ?', [$needle]);
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

        $deliveryStatus = trim((string) ($filters['delivery_status'] ?? ''));
        if ($deliveryStatus !== '') {
            $query->where(function (Builder $builder) use ($deliveryStatus): void {
                match ($deliveryStatus) {
                    'returned' => $builder->where('status', Invoice::STATUS_RETURNED),
                    'waiting' => $builder->whereIn('status', [
                        Invoice::STATUS_CONFIRMED,
                        Invoice::STATUS_PARTIALLY_PAID,
                        Invoice::STATUS_PAID,
                    ]),
                    'received' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->whereDate('occasion_datetime', '>', Carbon::today())
                        ->where(function (Builder $lateQuery): void {
                            $lateQuery
                                ->whereNull('rent_end_date')
                                ->orWhereDate('rent_end_date', '>=', Carbon::today());
                        }),
                    'delivered' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->where(function (Builder $deliveredQuery): void {
                            $deliveredQuery
                                ->where(function (Builder $occasionQuery): void {
                                    $occasionQuery
                                        ->whereNull('occasion_datetime')
                                        ->orWhereDate('occasion_datetime', '<=', Carbon::today());
                                })
                                ->where(function (Builder $lateQuery): void {
                                    $lateQuery
                                        ->whereNull('rent_end_date')
                                        ->orWhereDate('rent_end_date', '>=', Carbon::today());
                                });
                        }),
                    'late' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->whereDate('rent_end_date', '<', Carbon::today()),
                    default => null,
                };
            });
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $query->where('created_by', $employeeId);
        }

        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $eventFrom = trim((string) ($filters['event_date_from'] ?? ''));
        if ($eventFrom !== '') {
            $query->whereDate('occasion_datetime', '>=', $eventFrom);
        }

        $eventTo = trim((string) ($filters['event_date_to'] ?? ''));
        if ($eventTo !== '') {
            $query->whereDate('occasion_datetime', '<=', $eventTo);
        }

        return $query;
    }
}
