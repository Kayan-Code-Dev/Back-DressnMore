<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Dress;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DressService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Dress::query()
            ->with(['category', 'subcategory', 'branch'])
            ->latest('id');

        $searchTerm = trim((string) ($filters['search'] ?? ''));
        if ($searchTerm !== '') {
            $wildcard = '%'.mb_strtolower($searchTerm).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(code) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(color) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(size) LIKE ?', [$wildcard])
                    ->orWhereHas('category', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$wildcard]))
                    ->orWhereHas('subcategory', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$wildcard]));
            });
        }

        $this->applyExactFilter($query, 'dress_category_id', $filters['dress_category_id'] ?? null);
        $this->applyExactFilter($query, 'dress_subcategory_id', $filters['dress_subcategory_id'] ?? null);
        $this->applyExactFilter($query, 'id', $filters['id'] ?? null);
        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        $this->applyExactFilter($query, 'entity_type', $filters['entity_type'] ?? null);
        $this->applyExactFilter($query, 'entity_id', $filters['entity_id'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
        $this->applyExactFilter($query, 'code', $filters['code'] ?? null);
        $this->applyExactFilter($query, 'name', $filters['name'] ?? null);
        $this->applyExactFilter($query, 'color', $filters['color'] ?? null);
        $this->applyExactFilter($query, 'size', $filters['size'] ?? null);
        $this->applyExactFilter($query, 'delivery_date', $filters['delivery_date'] ?? null);
        $this->applyExactFilter($query, 'days_of_rent', $filters['days_of_rent'] ?? null);

        if (isset($filters['category_id'])) {
            $this->applyExactFilter($query, 'dress_category_id', $filters['category_id']);
        }

        if (isset($filters['subcat_id'])) {
            $this->applyExactFilter($query, 'dress_subcategory_id', $filters['subcat_id']);
        }

        $createdFrom = trim((string) ($filters['created_from'] ?? ''));
        if ($createdFrom !== '') {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        $createdTo = trim((string) ($filters['created_to'] ?? ''));
        if ($createdTo !== '') {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        $occasionDatetime = trim((string) ($filters['occasion_datetime'] ?? ''));
        if ($occasionDatetime !== '') {
            $query->whereDate('occasion_datetime', '=', $occasionDatetime);
        }

        $visitDatetime = trim((string) ($filters['visit_datetime'] ?? ''));
        if ($visitDatetime !== '') {
            $query->whereDate('visit_datetime', '=', $visitDatetime);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): Dress
    {
        /** @var Dress $dress */
        $dress = DB::connection('tenant')->transaction(function () use ($data, $actorId): Dress {
            $dress = Dress::query()->create($data);

            $this->inventoryService->recordMovement(
                dress: $dress,
                type: InventoryMovement::TYPE_CREATED,
                reason: 'Dress created',
                notes: $dress->notes,
                createdBy: $actorId,
            );

            return $dress;
        });

        return $dress->load(['category', 'subcategory', 'branch']);
    }

    public function findOrFail(int $dressId): Dress
    {
        return Dress::query()->with(['category', 'subcategory', 'branch'])->findOrFail($dressId);
    }

    public function update(Dress $dress, array $data, ?int $actorId = null): Dress
    {
        $originalStatus = (string) $dress->status;
        $newStatus = (string) ($data['status'] ?? $originalStatus);

        /** @var Dress $updatedDress */
        $updatedDress = DB::connection('tenant')->transaction(function () use ($dress, $data, $actorId, $originalStatus, $newStatus): Dress {
            $dress->fill($data);
            $dress->save();

            if ($newStatus !== $originalStatus) {
                $this->inventoryService->recordMovement(
                    dress: $dress,
                    type: InventoryMovement::TYPE_STATUS_CHANGED,
                    reason: sprintf('Status changed from %s to %s', $originalStatus, $newStatus),
                    notes: $dress->notes,
                    createdBy: $actorId,
                );
            }

            return $dress;
        });

        return $updatedDress->refresh()->load(['category', 'subcategory', 'branch']);
    }

    public function delete(Dress $dress): void
    {
        $dress->delete();
    }

    public function orderHistory(Dress $dress, int $perPage = 15): LengthAwarePaginator
    {
        return InvoiceItem::query()
            ->where('dress_id', $dress->id)
            ->with('invoice')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{ranges:list<array{invoice_id:int,invoice_number:?string,start_date:?string,end_date:?string}>,days:list<string>}
     */
    public function unavailableDays(Dress $dress): array
    {
        $invoices = Invoice::query()
            ->where('type', Invoice::TYPE_RENT)
            ->whereIn('status', [
                Invoice::STATUS_CONFIRMED,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_PAID,
                Invoice::STATUS_DELIVERED,
                Invoice::STATUS_RETURNED,
            ])
            ->whereHas('items', function (Builder $builder) use ($dress): void {
                $builder->where('dress_id', $dress->id);
            })
            ->orderBy('rent_start_date')
            ->get(['id', 'invoice_number', 'rent_start_date', 'rent_end_date']);

        $ranges = [];
        $days = [];

        foreach ($invoices as $invoice) {
            $ranges[] = [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'start_date' => $invoice->rent_start_date?->toDateString(),
                'end_date' => $invoice->rent_end_date?->toDateString(),
            ];

            if ($invoice->rent_start_date === null || $invoice->rent_end_date === null) {
                continue;
            }

            $cursor = $invoice->rent_start_date->copy();
            $endDate = $invoice->rent_end_date->copy();
            while ($cursor->lte($endDate)) {
                $days[] = $cursor->toDateString();
                $cursor->addDay();
            }
        }

        return [
            'ranges' => $ranges,
            'days' => array_values(array_unique($days)),
        ];
    }

    public function availableForDate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $startDate = (string) ($filters['start_date'] ?? $filters['date'] ?? '');
        $endDate = (string) ($filters['end_date'] ?? $filters['date'] ?? '');

        $query = Dress::query()
            ->with(['category', 'subcategory', 'branch'])
            ->latest('id');

        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        if (isset($filters['category_id'])) {
            $this->applyExactFilter($query, 'dress_category_id', $filters['category_id']);
        }
        if (isset($filters['subcat_id'])) {
            $this->applyExactFilter($query, 'dress_subcategory_id', $filters['subcat_id']);
        }
        if (isset($filters['status'])) {
            $this->applyExactFilter($query, 'status', $filters['status']);
        } else {
            $query->where('status', Dress::STATUS_AVAILABLE);
        }

        if ($startDate !== '' && $endDate !== '') {
            $blockedDressIds = InvoiceItem::query()
                ->whereNotNull('dress_id')
                ->whereHas('invoice', function (Builder $builder) use ($startDate, $endDate): void {
                    $builder->where('type', Invoice::TYPE_RENT)
                        ->whereIn('status', [
                            Invoice::STATUS_CONFIRMED,
                            Invoice::STATUS_PARTIALLY_PAID,
                            Invoice::STATUS_PAID,
                            Invoice::STATUS_DELIVERED,
                        ])
                        ->whereDate('rent_start_date', '<=', $endDate)
                        ->whereDate('rent_end_date', '>=', $startDate);
                })
                ->pluck('dress_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($blockedDressIds !== []) {
                $query->whereNotIn('id', $blockedDressIds);
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (Dress $dress): array {
            return [
                $dress->id,
                $dress->code,
                $dress->name,
                $dress->status,
                $dress->branch_id,
                $dress->dress_category_id,
                $dress->dress_subcategory_id,
                $dress->entity_type,
                $dress->entity_id,
                $dress->rental_price,
                $dress->sale_price,
                $dress->created_at?->toDateTimeString(),
            ];
        }, $rows);
    }

    private function applyExactFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return;
        }

        $query->where($column, $normalized);
    }
}
