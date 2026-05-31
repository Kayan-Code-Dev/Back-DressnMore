<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Factory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FactoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Factory::query()->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(factory_code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$needle]);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
