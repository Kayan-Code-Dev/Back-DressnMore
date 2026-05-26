<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private readonly CashMovementService $cashMovementService)
    {
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with('category')
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(description) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(reference) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$wildcard]);
            });
        }

        $this->applyExactFilter($query, 'expense_category_id', $filters['expense_category_id'] ?? null);
        $this->applyExactFilter($query, 'method', $filters['method'] ?? null);

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): Expense
    {
        /** @var Expense $expense */
        $expense = DB::connection('tenant')->transaction(function () use ($data, $actorId): Expense {
            $expense = Expense::query()->create([
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->cashMovementService->syncExpenseMovement($expense, $actorId);

            return $expense;
        });

        return $expense->load('category');
    }

    public function findOrFail(int $expenseId): Expense
    {
        return Expense::query()
            ->with('category')
            ->findOrFail($expenseId);
    }

    public function update(Expense $expense, array $data, ?int $actorId = null): Expense
    {
        /** @var Expense $updatedExpense */
        $updatedExpense = DB::connection('tenant')->transaction(function () use ($expense, $data, $actorId): Expense {
            $expense->fill([
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $expense->created_by ?? $actorId,
            ]);
            $expense->save();

            $this->cashMovementService->syncExpenseMovement($expense, $actorId);

            return $expense->refresh();
        });

        return $updatedExpense->load('category');
    }

    public function delete(Expense $expense): void
    {
        DB::connection('tenant')->transaction(function () use ($expense): void {
            $expense->delete();
            $this->cashMovementService->softDeleteExpenseMovement($expense);
        });
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
