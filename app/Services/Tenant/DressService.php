<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Dress;
use App\Models\Tenant\InventoryMovement;
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
        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
        $this->applyExactFilter($query, 'color', $filters['color'] ?? null);
        $this->applyExactFilter($query, 'size', $filters['size'] ?? null);

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
