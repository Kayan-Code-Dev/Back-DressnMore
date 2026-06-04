<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Expense;
use App\Models\Tenant\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly CashMovementService $cashMovementService,
        private readonly JournalEntryPostingService $journalEntryPostingService,
    ) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with(['category', 'branch', 'cashbox'])
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
        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        $this->applyExactFilter($query, 'cashbox_id', $filters['cashbox_id'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
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
            $status = (string) ($data['status'] ?? Expense::STATUS_PAID);
            $expense = Expense::query()->create([
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'cashbox_id' => $data['cashbox_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'status' => $status,
                'method' => $data['method'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'created_by' => $actorId,
                'paid_at' => $status === Expense::STATUS_PAID ? ($data['paid_at'] ?? Carbon::now()) : null,
            ]);

            if ($status === Expense::STATUS_PAID) {
                $this->cashMovementService->syncExpenseMovement($expense, $actorId);
            }

            return $expense;
        });

        if ($expense->status === Expense::STATUS_PAID) {
            $this->journalEntryPostingService->postFromExpense($expense, $actorId);
        }

        return $expense->load(['category', 'branch', 'cashbox']);
    }

    public function findOrFail(int $expenseId): Expense
    {
        return Expense::query()
            ->with(['category', 'branch', 'cashbox'])
            ->findOrFail($expenseId);
    }

    public function update(Expense $expense, array $data, ?int $actorId = null): Expense
    {
        $allowFinancialEdit = (bool) ($data['allow_financial_edit'] ?? false);
        if (
            $expense->status === Expense::STATUS_PAID
            && $expense->paid_at !== null
            && ! $allowFinancialEdit
            && $this->financialDataChanged($expense, $data)
        ) {
            throw ValidationException::withMessages([
                'expense' => ['Paid expense financial fields cannot be updated without allow_financial_edit'],
            ]);
        }

        /** @var Expense $updatedExpense */
        $updatedExpense = DB::connection('tenant')->transaction(function () use ($expense, $data, $actorId): Expense {
            $status = (string) ($data['status'] ?? $expense->status);
            $expense->fill([
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'cashbox_id' => $data['cashbox_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'status' => $status,
                'method' => $data['method'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? $expense->transaction_id,
                'created_by' => $expense->created_by ?? $actorId,
            ]);

            if ($status === Expense::STATUS_CANCELLED) {
                $expense->cancelled_at = $expense->cancelled_at ?? Carbon::now();
            } elseif ($status === Expense::STATUS_PAID) {
                $expense->paid_at = $expense->paid_at ?? Carbon::now();
                $expense->cancelled_at = null;
            }

            $expense->save();

            if ($status === Expense::STATUS_PAID) {
                $this->cashMovementService->syncExpenseMovement($expense, $actorId);
            } else {
                $this->cashMovementService->softDeleteExpenseMovement($expense);
            }

            return $expense->refresh();
        });

        if ($updatedExpense->status === Expense::STATUS_PAID) {
            $this->journalEntryPostingService->postFromExpense($updatedExpense, $actorId);
        } elseif ($updatedExpense->status === Expense::STATUS_CANCELLED) {
            $this->journalEntryPostingService->cancelBySource(JournalEntry::SOURCE_EXPENSE, (int) $updatedExpense->id, $actorId);
        }

        return $updatedExpense->load(['category', 'branch', 'cashbox']);
    }

    public function delete(Expense $expense): void
    {
        DB::connection('tenant')->transaction(function () use ($expense): void {
            $expense->delete();
            $this->cashMovementService->softDeleteExpenseMovement($expense);
        });
    }

    public function approve(Expense $expense, ?int $actorId = null): Expense
    {
        if ($expense->status === Expense::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'expense' => ['Cancelled expense cannot be approved'],
            ]);
        }

        if ($expense->status === Expense::STATUS_PAID) {
            return $expense->refresh()->load(['category', 'branch', 'cashbox']);
        }

        $expense->status = Expense::STATUS_APPROVED;
        $expense->approved_by = $actorId;
        $expense->save();

        return $expense->refresh()->load(['category', 'branch', 'cashbox']);
    }

    public function cancel(Expense $expense, ?string $notes = null): Expense
    {
        if ($expense->status === Expense::STATUS_PAID) {
            throw ValidationException::withMessages([
                'expense' => ['Paid expense cannot be cancelled'],
            ]);
        }

        $expense->status = Expense::STATUS_CANCELLED;
        $expense->cancelled_at = Carbon::now();
        if ($notes !== null && trim($notes) !== '') {
            $expense->notes = $notes;
        }
        $expense->save();

        $this->cashMovementService->softDeleteExpenseMovement($expense);

        return $expense->refresh()->load(['category', 'branch', 'cashbox']);
    }

    public function pay(Expense $expense, array $data, ?int $actorId = null): Expense
    {
        /** @var Expense $paidExpense */
        $paidExpense = DB::connection('tenant')->transaction(function () use ($expense, $data, $actorId): Expense {
            /** @var Expense $lockedExpense */
            $lockedExpense = Expense::query()
                ->whereKey($expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExpense->status === Expense::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'expense' => ['Cancelled expense cannot be paid'],
                ]);
            }

            if ($lockedExpense->status === Expense::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'expense' => ['Expense is already paid. Reverse or cancel the posted payment before changing payment details.'],
                ]);
            }

            if (isset($data['cashbox_id'])) {
                $lockedExpense->cashbox_id = $data['cashbox_id'];
            }
            if (isset($data['method'])) {
                $lockedExpense->method = $data['method'];
            }
            if (isset($data['transaction_id'])) {
                $lockedExpense->transaction_id = $data['transaction_id'];
            }
            if (isset($data['notes'])) {
                $lockedExpense->notes = $data['notes'];
            }

            $lockedExpense->status = Expense::STATUS_PAID;
            $lockedExpense->paid_at = $data['paid_at'] ?? Carbon::now();
            $lockedExpense->cancelled_at = null;
            $lockedExpense->save();

            $this->cashMovementService->syncExpenseMovement($lockedExpense, $actorId);

            return $lockedExpense->refresh();
        });

        $this->journalEntryPostingService->postFromExpense($paidExpense, $actorId);

        return $paidExpense->load(['category', 'branch', 'cashbox']);
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(array $filters): array
    {
        $query = Expense::query();
        $this->applyExactFilter($query, 'expense_category_id', $filters['expense_category_id'] ?? null);

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        $byCategory = (clone $query)
            ->selectRaw('expense_category_id, COALESCE(SUM(amount),0) as total_amount')
            ->groupBy('expense_category_id')
            ->get()
            ->map(fn ($row): array => [
                'expense_category_id' => $row->expense_category_id,
                'total_amount' => round((float) $row->total_amount, 2),
            ])
            ->values()
            ->all();

        return [
            'total_amount' => round((float) (clone $query)->sum('amount'), 2),
            'pending_amount' => round((float) (clone $query)->where('status', Expense::STATUS_PENDING)->sum('amount'), 2),
            'approved_amount' => round((float) (clone $query)->where('status', Expense::STATUS_APPROVED)->sum('amount'), 2),
            'paid_amount' => round((float) (clone $query)->where('status', Expense::STATUS_PAID)->sum('amount'), 2),
            'cancelled_amount' => round((float) (clone $query)->where('status', Expense::STATUS_CANCELLED)->sum('amount'), 2),
            'by_category' => $byCategory,
        ];
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (Expense $expense): array {
            return [
                $expense->id,
                $expense->expense_category_id,
                $expense->branch_id,
                $expense->cashbox_id,
                $expense->status,
                $expense->amount,
                $expense->method,
                $expense->vendor,
                $expense->reference_number,
                $expense->expense_date?->toDateString(),
                $expense->transaction_id,
            ];
        }, $rows);
    }

    private function financialDataChanged(Expense $expense, array $data): bool
    {
        $fields = ['amount', 'method', 'reference', 'reference_number', 'expense_date', 'cashbox_id', 'branch_id'];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $incoming = $data[$field];
            $current = $expense->{$field};
            if ((string) $incoming !== (string) $current) {
                return true;
            }
        }

        return false;
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
