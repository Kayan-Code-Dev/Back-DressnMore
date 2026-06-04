<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CashMovementService
{
    public function __construct(private readonly JournalEntryPostingService $journalEntryPostingService) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CashMovement::query()
            ->with(['cashbox.branch'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $wildcard = '%'.mb_strtolower($search).'%';
                $builder->whereRaw('LOWER(reference) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$wildcard]);
            });
        }

        $this->applyExactFilter($query, 'type', $filters['type'] ?? null);
        $this->applyExactFilter($query, 'direction', $filters['direction'] ?? null);
        $this->applyExactFilter($query, 'method', $filters['method'] ?? null);
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

        if (($filters['is_reversed'] ?? null) !== null && trim((string) $filters['is_reversed']) !== '') {
            $query->where('is_reversed', filter_var($filters['is_reversed'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function createManual(array $data, ?int $actorId = null): CashMovement
    {
        $type = (string) $data['type'];
        $direction = (string) $data['direction'];
        $this->ensureManualDirectionValid($type, $direction);

        return $this->createMovement([
            'type' => $type,
            'direction' => $direction,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'] ?? null,
            'cashbox_id' => $data['cashbox_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'movement_date' => $data['movement_date'] ?? Carbon::now(),
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_reversed' => false,
            'created_by' => $actorId,
        ]);
    }

    public function syncExpenseMovement(Expense $expense, ?int $actorId = null): CashMovement
    {
        $movement = CashMovement::withTrashed()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->first();

        $payload = [
            'type' => CashMovement::TYPE_EXPENSE,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => round((float) $expense->amount, 2),
            'method' => $expense->method,
            'cashbox_id' => $expense->cashbox_id,
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expense->id,
            'reference' => $expense->reference,
            'movement_date' => $expense->expense_date?->startOfDay() ?? Carbon::now(),
            'description' => $expense->description,
            'notes' => $expense->notes,
            'created_by' => $actorId ?? $expense->created_by,
            'is_reversed' => false,
        ];

        if ($movement instanceof CashMovement) {
            $oldCashboxId = $movement->cashbox_id;
            if ($movement->trashed()) {
                $movement->restore();
            }

            $movement->fill($payload);
            $movement->save();
            $newCashboxId = $movement->cashbox_id;
            if ($oldCashboxId !== null && $oldCashboxId !== $newCashboxId) {
                $this->syncCashboxBalance((int) $oldCashboxId);
            }

            $balanceAfter = $newCashboxId !== null ? $this->syncCashboxBalance((int) $newCashboxId) : null;
            if ($balanceAfter !== null) {
                $movement->balance_after = $balanceAfter;
                $movement->save();
            }

            return $movement->refresh();
        }

        return $this->createMovement($payload);
    }

    public function softDeleteExpenseMovement(Expense $expense): void
    {
        $cashboxIds = CashMovement::query()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->pluck('cashbox_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        CashMovement::query()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->delete();

        foreach ($cashboxIds as $cashboxId) {
            $this->syncCashboxBalance((int) $cashboxId);
        }
    }

    public function recordInvoicePayment(InvoicePayment $payment, ?int $actorId = null): CashMovement
    {
        return $this->createMovement([
            'type' => CashMovement::TYPE_INVOICE_PAYMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->method,
            'cashbox_id' => null,
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $payment->id,
            'reference' => $payment->reference,
            'movement_date' => $payment->paid_at ?? Carbon::now(),
            'description' => 'Invoice payment received',
            'notes' => $payment->notes,
            'is_reversed' => false,
            'created_by' => $actorId ?? $payment->created_by,
        ]);
    }

    public function recordSecurityDepositCollection(
        SecurityDepositTransaction $transaction,
        ?int $actorId = null
    ): CashMovement {
        return $this->createMovement([
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_COLLECTION,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => round((float) $transaction->amount, 2),
            'method' => null,
            'cashbox_id' => null,
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
            'reference_id' => $transaction->id,
            'reference' => $transaction->reason,
            'movement_date' => Carbon::now(),
            'description' => 'Security deposit collection',
            'notes' => $transaction->notes,
            'is_reversed' => false,
            'created_by' => $actorId ?? $transaction->created_by,
        ]);
    }

    public function recordSecurityDepositRefund(
        SecurityDepositTransaction $transaction,
        ?int $actorId = null
    ): CashMovement {
        return $this->createMovement([
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_REFUND,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => round((float) $transaction->amount, 2),
            'method' => null,
            'cashbox_id' => null,
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
            'reference_id' => $transaction->id,
            'reference' => $transaction->reason,
            'movement_date' => Carbon::now(),
            'description' => 'Security deposit refund',
            'notes' => $transaction->notes,
            'is_reversed' => false,
            'created_by' => $actorId ?? $transaction->created_by,
        ]);
    }

    public function recordSecurityDepositDeduction(
        SecurityDepositTransaction $transaction,
        ?int $actorId = null
    ): CashMovement {
        return $this->createMovement([
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_DEDUCTION,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => round((float) $transaction->amount, 2),
            'method' => null,
            'cashbox_id' => null,
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
            'reference_id' => $transaction->id,
            'reference' => $transaction->reason,
            'movement_date' => Carbon::now(),
            'description' => 'Security deposit deduction',
            'notes' => $transaction->notes,
            'is_reversed' => false,
            'created_by' => $actorId ?? $transaction->created_by,
        ]);
    }

    public function recordSupplierPayment(SupplierPayment $payment, ?int $actorId = null): CashMovement
    {
        return $this->createMovement([
            'type' => CashMovement::TYPE_SUPPLIER_PAYMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->method,
            'cashbox_id' => null,
            'reference_type' => CashMovement::REFERENCE_SUPPLIER_PAYMENT,
            'reference_id' => $payment->id,
            'reference' => $payment->reference,
            'movement_date' => $payment->paid_at ?? Carbon::now(),
            'description' => 'Supplier payment',
            'notes' => $payment->notes,
            'is_reversed' => false,
            'created_by' => $actorId ?? $payment->created_by,
        ]);
    }

    public function markReferenceReversed(string $referenceType, int $referenceId): void
    {
        $movements = CashMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('is_reversed', false)
            ->get();

        foreach ($movements as $movement) {
            $movement->is_reversed = true;
            $movement->save();
            if ($movement->cashbox_id !== null) {
                $this->syncCashboxBalance((int) $movement->cashbox_id);
            }
        }
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

    private function ensureManualDirectionValid(string $type, string $direction): void
    {
        if ($type === CashMovement::TYPE_INCOME && $direction !== CashMovement::DIRECTION_IN) {
            throw ValidationException::withMessages([
                'direction' => ['Income cash movement direction must be in'],
            ]);
        }

        if ($type === CashMovement::TYPE_EXPENSE && $direction !== CashMovement::DIRECTION_OUT) {
            throw ValidationException::withMessages([
                'direction' => ['Expense cash movement direction must be out'],
            ]);
        }
    }

    private function createMovement(array $payload): CashMovement
    {
        $referenceType = $payload['reference_type'] ?? null;
        $referenceId = $payload['reference_id'] ?? null;
        if ($referenceType !== null && $referenceId !== null) {
            $existing = CashMovement::withTrashed()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing instanceof CashMovement) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing->refresh();
            }
        }

        /** @var CashMovement $movement */
        try {
            $movement = CashMovement::query()->create($payload);
        } catch (QueryException $exception) {
            if ($this->isUniqueReferenceViolation($exception) && $referenceType !== null && $referenceId !== null) {
                /** @var CashMovement|null $existing */
                $existing = CashMovement::withTrashed()
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->first();

                if ($existing instanceof CashMovement) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    return $existing->refresh();
                }
            }

            throw $exception;
        }

        if ($movement->cashbox_id !== null) {
            $movement->balance_after = $this->syncCashboxBalance((int) $movement->cashbox_id);
            $movement->save();
        }

        $this->journalEntryPostingService->postFromCashMovement(
            $movement->refresh(),
            $payload['created_by'] ?? null,
        );

        return $movement;
    }

    private function isUniqueReferenceViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '2067'], true);
    }

    private function syncCashboxBalance(int $cashboxId): float
    {
        $cashbox = Cashbox::query()->find($cashboxId);
        if (! $cashbox instanceof Cashbox) {
            return 0;
        }

        $in = (float) CashMovement::query()
            ->where('cashbox_id', $cashboxId)
            ->where('is_reversed', false)
            ->where('direction', CashMovement::DIRECTION_IN)
            ->sum('amount');
        $out = (float) CashMovement::query()
            ->where('cashbox_id', $cashboxId)
            ->where('is_reversed', false)
            ->where('direction', CashMovement::DIRECTION_OUT)
            ->sum('amount');

        $cashbox->current_balance = round((float) $cashbox->initial_balance + $in - $out, 2);
        $cashbox->save();

        return (float) $cashbox->current_balance;
    }
}
