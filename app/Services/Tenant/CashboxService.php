<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CashboxService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Cashbox::query()->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$wildcard]);
            });
        }

        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): Cashbox
    {
        $initialBalance = round((float) ($data['initial_balance'] ?? 0), 2);

        return Cashbox::query()->create([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'] ?? null,
            'initial_balance' => $initialBalance,
            'current_balance' => $initialBalance,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function findOrFail(int $cashboxId): Cashbox
    {
        return Cashbox::query()->findOrFail($cashboxId);
    }

    public function update(Cashbox $cashbox, array $data): Cashbox
    {
        $cashbox->fill([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'] ?? null,
            'initial_balance' => round((float) ($data['initial_balance'] ?? 0), 2),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $cashbox->save();

        return $this->recalculate($cashbox);
    }

    public function delete(Cashbox $cashbox): void
    {
        $cashbox->delete();
    }

    public function transactions(Cashbox $cashbox, int $perPage = 15): LengthAwarePaginator
    {
        return CashMovement::query()
            ->where('cashbox_id', $cashbox->id)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function recalculate(Cashbox $cashbox): Cashbox
    {
        $totals = CashMovement::query()
            ->where('cashbox_id', $cashbox->id)
            ->where('is_reversed', false)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) as total_in,
                 COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) as total_out',
                [CashMovement::DIRECTION_IN, CashMovement::DIRECTION_OUT]
            )
            ->first();

        $totalIn = round((float) ($totals?->total_in ?? 0), 2);
        $totalOut = round((float) ($totals?->total_out ?? 0), 2);
        $cashbox->current_balance = round((float) $cashbox->initial_balance + $totalIn - $totalOut, 2);
        $cashbox->save();

        return $cashbox->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function dailySummary(array $filters): array
    {
        $query = CashMovement::query()->whereNotNull('cashbox_id')->where('is_reversed', false);
        $this->applyExactFilter($query, 'cashbox_id', $filters['cashbox_id'] ?? null);
        if (($filters['branch_id'] ?? null) !== null && trim((string) $filters['branch_id']) !== '') {
            $branchId = (int) $filters['branch_id'];
            $query->whereHas('cashbox', fn (Builder $builder) => $builder->where('branch_id', $branchId));
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        return [
            'total_in' => round((float) (clone $query)->where('direction', CashMovement::DIRECTION_IN)->sum('amount'), 2),
            'total_out' => round((float) (clone $query)->where('direction', CashMovement::DIRECTION_OUT)->sum('amount'), 2),
            'net' => round((float) (clone $query)->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) as net',
                [CashMovement::DIRECTION_IN]
            )->value('net'), 2),
        ];
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (Cashbox $cashbox): array {
            return [
                $cashbox->id,
                $cashbox->name,
                $cashbox->branch_id,
                $cashbox->initial_balance,
                $cashbox->current_balance,
                $cashbox->is_active ? 'active' : 'inactive',
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
