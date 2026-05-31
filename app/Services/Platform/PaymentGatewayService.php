<?php

namespace App\Services\Platform;

use App\Models\Central\PaymentGateway;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentGatewayService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return list<PaymentGateway>
     */
    public function allActive(): array
    {
        return PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->all();
    }

    public function findOrFail(int $gatewayId): PaymentGateway
    {
        return PaymentGateway::query()->findOrFail($gatewayId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentGateway
    {
        return PaymentGateway::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentGateway $gateway, array $data): PaymentGateway
    {
        $gateway->fill($data);
        $gateway->save();

        return $gateway->refresh();
    }

    public function delete(PaymentGateway $gateway): void
    {
        $gateway->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PaymentGateway>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = PaymentGateway::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(account_holder) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(account_number) LIKE ?', [$needle]);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }
}
