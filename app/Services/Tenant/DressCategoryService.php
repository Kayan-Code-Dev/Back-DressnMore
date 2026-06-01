<?php

namespace App\Services\Tenant;

use App\Enums\CustomerStatus;
use App\Models\Tenant\DressCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DressCategoryService
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        mixed $parentId = null,
        bool $onlyParents = false,
        bool $onlyChildren = false,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = DressCategory::query()
            ->with(['parent', 'children'])
            ->latest('id');

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($searchTerm).'%']);
        }

        $statusValue = trim((string) $status);
        if ($statusValue !== '') {
            $query->where('status', $statusValue);
        }

        $parentIdValue = trim((string) $parentId);
        if ($parentIdValue !== '') {
            $query->where('parent_id', (int) $parentIdValue);
        }

        if ($onlyParents) {
            $query->whereNull('parent_id');
        }

        if ($onlyChildren) {
            $query->whereNotNull('parent_id');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): DressCategory
    {
        $category = DressCategory::query()->create($this->filterCategoryInput($data));

        return $category->load(['parent', 'children']);
    }

    public function findOrFail(int $categoryId): DressCategory
    {
        return DressCategory::query()
            ->with(['parent', 'children'])
            ->findOrFail($categoryId);
    }

    public function update(DressCategory $category, array $data): DressCategory
    {
        $category->fill($this->filterCategoryInput($data));
        $category->save();

        return $category->refresh()->load(['parent', 'children']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterCategoryInput(array $data, bool $isCreate = false): array
    {
        $allowed = ['name', 'parent_id', 'slug', 'description', 'status'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if ($isCreate && ! array_key_exists('status', $filtered)) {
            $filtered['status'] = CustomerStatus::ACTIVE->value;
        }

        if (array_key_exists('name', $filtered) && ! array_key_exists('slug', $filtered)) {
            $filtered['slug'] = Str::slug((string) $filtered['name']);
        }

        return $filtered;
    }

    public function delete(DressCategory $category): void
    {
        $category->delete();
    }
}
