<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseCategoryService
{
    public function paginate(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = ExpenseCategory::query()->latest('id');

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

    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::query()->create($data);
    }

    public function findOrFail(int $expenseCategoryId): ExpenseCategory
    {
        return ExpenseCategory::query()->findOrFail($expenseCategoryId);
    }

    public function update(ExpenseCategory $expenseCategory, array $data): ExpenseCategory
    {
        $expenseCategory->fill($data);
        $expenseCategory->save();

        return $expenseCategory->refresh();
    }

    public function delete(ExpenseCategory $expenseCategory): void
    {
        $expenseCategory->delete();
    }
}
