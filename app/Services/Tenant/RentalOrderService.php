<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Support\Tenant\RentalOrderPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RentalOrderService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)->latest('id');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findOrFail(int $invoiceId): Invoice
    {
        return $this->baseQuery([])->findOrFail($invoiceId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function stats(array $filters = []): array
    {
        $invoices = $this->baseQuery($filters)->get();

        $active = 0;
        $returned = 0;
        $overdue = 0;
        $revenue = 0.0;
        $collected = 0.0;
        $remaining = 0.0;

        foreach ($invoices as $invoice) {
            $status = RentalOrderPresenter::mapStatus($invoice);
            if ($status === 'active') {
                $active++;
            }
            if ($status === 'returned') {
                $returned++;
            }
            if ($status === 'overdue') {
                $overdue++;
            }

            $revenue += (float) $invoice->total;
            $collected += (float) $invoice->paid_amount;
            $remaining += (float) $invoice->remaining_amount;
        }

        return [
            'total' => $invoices->count(),
            'active' => $active,
            'returned' => $returned,
            'overdue' => $overdue,
            'revenue' => round($revenue, 2),
            'collected' => round($collected, 2),
            'remaining' => round($remaining, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['customer', 'branch', 'items.dress', 'payments', 'createdBy'])
            ->where('type', Invoice::TYPE_RENT);

        $search = trim((string) ($filters['search'] ?? ($filters['client_name'] ?? '')));
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

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where(function (Builder $builder) use ($status): void {
                match ($status) {
                    'cancelled' => $builder->where('status', Invoice::STATUS_CANCELLED),
                    'returned' => $builder->where('status', Invoice::STATUS_RETURNED),
                    'overdue' => $builder
                        ->where('status', Invoice::STATUS_DELIVERED)
                        ->whereDate('rent_end_date', '<', Carbon::today()),
                    'active' => $builder->where(function (Builder $activeQuery): void {
                        $activeQuery
                            ->whereIn('status', [
                                Invoice::STATUS_CONFIRMED,
                                Invoice::STATUS_PARTIALLY_PAID,
                                Invoice::STATUS_PAID,
                            ])
                            ->orWhere(function (Builder $deliveredQuery): void {
                                $deliveredQuery
                                    ->where('status', Invoice::STATUS_DELIVERED)
                                    ->whereDate('rent_end_date', '>=', Carbon::today());
                            });
                    }),
                    'pending' => $builder->where('status', Invoice::STATUS_DRAFT),
                    default => null,
                };
            });
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
