<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Support\Tenant\InvoiceReturnPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InvoiceReturnListService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderBy('rent_end_date')
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

        $lateReturns = 0;
        $returned = 0;
        $waiting = 0;
        $maxDelay = 0;
        $penaltiesTotal = 0.0;
        $penaltiesDue = 0.0;
        $penaltiesCollected = 0.0;
        $revenue = 0.0;
        $distribution = [
            'waiting' => 0,
            'returned' => 0,
            'late' => 0,
        ];

        foreach ($invoices as $invoice) {
            $returnStatus = InvoiceReturnPresenter::mapReturnStatus($invoice);
            if (array_key_exists($returnStatus, $distribution)) {
                $distribution[$returnStatus]++;
            }

            if ($returnStatus === 'late') {
                $lateReturns++;
            }
            if ($returnStatus === 'returned') {
                $returned++;
            }
            if ($returnStatus === 'waiting') {
                $waiting++;
            }

            $delayDays = InvoiceReturnPresenter::delayDays($invoice, $returnStatus);
            $maxDelay = max($maxDelay, $delayDays);

            $penaltyPerDay = InvoiceReturnPresenter::penaltyPerDay($invoice);
            $penaltyAmount = InvoiceReturnPresenter::penaltyAmount($invoice, $returnStatus, $delayDays, $penaltyPerDay);
            $penaltyPaid = InvoiceReturnPresenter::penaltyPaid($invoice, $penaltyAmount, $returnStatus);

            $penaltiesTotal += $penaltyAmount;
            $penaltiesDue += max(0, $penaltyAmount - $penaltyPaid);
            $penaltiesCollected += $penaltyPaid;
            $revenue += (float) $invoice->total;
        }

        return [
            'total' => $invoices->count(),
            'late_returns' => $lateReturns,
            'returned' => $returned,
            'waiting' => $waiting,
            'max_delay_days' => $maxDelay,
            'penalties_total' => round($penaltiesTotal, 2),
            'penalties_due' => round($penaltiesDue, 2),
            'penalties_collected' => round($penaltiesCollected, 2),
            'revenue' => round($revenue, 2),
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
            ->with(['customer', 'branch', 'createdBy', 'items.dress', 'payments', 'deliveryRecords'])
            ->where('type', Invoice::TYPE_RENT)
            ->whereIn('status', [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED]);

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

        $returnStatus = trim((string) ($filters['return_status'] ?? ''));
        if ($returnStatus !== '') {
            $query->where(function (Builder $builder) use ($returnStatus): void {
                match ($returnStatus) {
                    'returned' => $builder->where('status', Invoice::STATUS_RETURNED),
                    'late' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->whereDate('rent_end_date', '<', Carbon::today()),
                    'waiting' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->where(function (Builder $waitingQuery): void {
                            $waitingQuery
                                ->whereNull('rent_end_date')
                                ->orWhereDate('rent_end_date', '>=', Carbon::today());
                        }),
                    default => null,
                };
            });
        }

        $returnType = trim((string) ($filters['return_type'] ?? ''));
        if ($returnType !== '') {
            $query->where(function (Builder $builder) use ($returnType): void {
                match ($returnType) {
                    'instant' => $builder->where('status', Invoice::STATUS_RETURNED),
                    'late' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->whereDate('rent_end_date', '<', Carbon::today()),
                    'scheduled' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->where(function (Builder $scheduledQuery): void {
                            $scheduledQuery
                                ->whereNull('rent_end_date')
                                ->orWhereDate('rent_end_date', '>=', Carbon::today());
                        }),
                    default => null,
                };
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

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $query->where('created_by', $employeeId);
        }

        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $returnFrom = trim((string) ($filters['return_date_from'] ?? ''));
        if ($returnFrom !== '') {
            $query->whereDate('rent_end_date', '>=', $returnFrom);
        }

        $returnTo = trim((string) ($filters['return_date_to'] ?? ''));
        if ($returnTo !== '') {
            $query->whereDate('rent_end_date', '<=', $returnTo);
        }

        return $query;
    }
}
