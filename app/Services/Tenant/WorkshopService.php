<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Workshop;
use App\Models\Tenant\WorkshopCloth;
use App\Models\Tenant\WorkshopTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WorkshopService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)->latest('id')->paginate($perPage)->withQueryString();
    }

    public function findOrFail(int $workshopId): Workshop
    {
        return Workshop::query()->findOrFail($workshopId);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateTransfers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkshopTransfer::query()->latest('id');

        if ($filters['workshop_id'] ?? null) {
            $query->where('workshop_id', (int) $filters['workshop_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCloths(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkshopCloth::query()->latest('updated_at');

        if ($filters['workshop_id'] ?? null) {
            $query->where('workshop_id', (int) $filters['workshop_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Workshop>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Workshop::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(workshop_code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$needle]);
            });
        }

        return $query;
    }
}
