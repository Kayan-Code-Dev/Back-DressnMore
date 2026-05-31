<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->withCount(['invoices as orders_count'])
            ->withSum('invoices as total_spent', 'total')
            ->latest('id');

        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') {
            $wildcard = '%'.mb_strtolower($term).'%';

            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone2) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$wildcard]);
            });
        }

        $this->applyExactFilter($query, 'id', $filters['id'] ?? null);
        $this->applyExactFilter($query, 'source', $filters['source'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);

        $dobFrom = trim((string) ($filters['date_of_birth_from'] ?? ''));
        if ($dobFrom !== '') {
            $query->whereDate('date_of_birth', '>=', $dobFrom);
        }

        $dobTo = trim((string) ($filters['date_of_birth_to'] ?? ''));
        if ($dobTo !== '') {
            $query->whereDate('date_of_birth', '<=', $dobTo);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $total = Customer::query()->count();
        $vip = Customer::query()->where('notes', 'like', '%[VIP]%')->count();
        $newThisMonth = Customer::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $totalSales = (float) Invoice::query()
            ->whereNotNull('customer_id')
            ->sum('total');

        return [
            'total' => $total,
            'vip' => $vip,
            'new_this_month' => $newThisMonth,
            'total_sales' => round($totalSales, 2),
        ];
    }

    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function findOrFail(int $customerId): Customer
    {
        return Customer::query()->findOrFail($customerId);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->save();

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (Customer $customer): array {
            return [
                $customer->id,
                $customer->name,
                $customer->date_of_birth?->toDateString(),
                $customer->source,
                $customer->phone,
                $customer->phone2,
                $customer->whatsapp,
                $customer->address,
                $customer->city_id,
                $customer->status,
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
