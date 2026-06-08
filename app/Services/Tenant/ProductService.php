<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('branch')
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle]);
            });
        }

        if (($filters['branch_id'] ?? null) !== null && trim((string) $filters['branch_id']) !== '') {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (($filters['is_active'] ?? null) !== null && trim((string) $filters['is_active']) !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): Product
    {
        return Product::query()->create([
            'branch_id' => $data['branch_id'],
            'sku' => trim((string) $data['sku']),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'cost_price' => round((float) ($data['cost_price'] ?? 0), 2),
            'sale_price' => round((float) ($data['sale_price'] ?? 0), 2),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $actorId,
        ])->load('branch');
    }

    public function findOrFail(int $productId): Product
    {
        return Product::query()->with('branch')->findOrFail($productId);
    }

    public function update(Product $product, array $data): Product
    {
        $product->fill([
            'branch_id' => $data['branch_id'],
            'sku' => trim((string) $data['sku']),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'cost_price' => round((float) ($data['cost_price'] ?? 0), 2),
            'sale_price' => round((float) ($data['sale_price'] ?? 0), 2),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $product->save();

        return $product->refresh()->load('branch');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
