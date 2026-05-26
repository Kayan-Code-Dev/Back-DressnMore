<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BranchService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Branch::query()->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(branch_code) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$wildcard]);
            });
        }

        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
        $this->applyExactFilter($query, 'city_id', $filters['city_id'] ?? null);
        $this->applyExactFilter($query, 'currency_id', $filters['currency_id'] ?? null);

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): Branch
    {
        return Branch::query()->create($this->payload($data));
    }

    public function findOrFail(int $branchId): Branch
    {
        return Branch::query()->findOrFail($branchId);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->fill($this->payload($data, $branch));
        $branch->save();

        return $branch->refresh();
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(function (Branch $branch): array {
            return [
                $branch->id,
                $branch->branch_code ?: $branch->code,
                $branch->name,
                $branch->phone,
                $branch->currency,
                $branch->address,
                $branch->status,
            ];
        }, $rows);
    }

    private function payload(array $data, ?Branch $branch = null): array
    {
        $branchCode = $data['branch_code'] ?? $branch?->branch_code ?? $branch?->code;

        return [
            'branch_code' => $branchCode,
            'code' => $branchCode,
            'name' => $data['name'],
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $branch?->phone,
            'vat_enabled' => array_key_exists('vat_enabled', $data)
                ? (bool) $data['vat_enabled']
                : (bool) ($branch?->vat_enabled ?? false),
            'vat_type' => array_key_exists('vat_type', $data) ? $data['vat_type'] : $branch?->vat_type,
            'vat_value' => array_key_exists('vat_value', $data)
                ? round((float) $data['vat_value'], 2)
                : $branch?->vat_value,
            'currency' => array_key_exists('currency', $data) ? $data['currency'] : $branch?->currency,
            'currency_id' => array_key_exists('currency_id', $data) ? $data['currency_id'] : $branch?->currency_id,
            'street' => array_key_exists('street', $data) ? $data['street'] : $branch?->street,
            'building' => array_key_exists('building', $data) ? $data['building'] : $branch?->building,
            'city_id' => array_key_exists('city_id', $data) ? $data['city_id'] : $branch?->city_id,
            'address' => array_key_exists('address', $data) ? $data['address'] : $branch?->address,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $branch?->notes,
            'inventory_name' => array_key_exists('inventory_name', $data) ? $data['inventory_name'] : $branch?->inventory_name,
            'image' => array_key_exists('image', $data) ? $data['image'] : $branch?->image,
            'status' => array_key_exists('status', $data) ? $data['status'] : ($branch?->status ?? Branch::STATUS_ACTIVE),
        ];
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
