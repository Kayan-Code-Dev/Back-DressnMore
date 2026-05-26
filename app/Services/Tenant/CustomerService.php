<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerService
{
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()->latest('id');

        $term = trim((string) $search);
        if ($term !== '') {
            $wildcard = '%'.mb_strtolower($term).'%';

            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$wildcard]);
            });
        }

        return $query->paginate($perPage)->withQueryString();
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
}
