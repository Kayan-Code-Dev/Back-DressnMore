<?php

namespace App\Services\Tenant;

use App\Models\Tenant\DressCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DressCategoryService
{
    public function paginate(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = DressCategory::query()->latest('id');

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($searchTerm).'%']);
        }

        $statusValue = trim((string) $status);
        if ($statusValue !== '') {
            $query->where('status', $statusValue);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): DressCategory
    {
        return DressCategory::query()->create($data);
    }

    public function findOrFail(int $categoryId): DressCategory
    {
        return DressCategory::query()->findOrFail($categoryId);
    }

    public function update(DressCategory $category, array $data): DressCategory
    {
        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function delete(DressCategory $category): void
    {
        $category->delete();
    }
}
