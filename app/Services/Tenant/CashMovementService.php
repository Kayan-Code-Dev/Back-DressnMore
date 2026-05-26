<?php

namespace App\Services\Tenant;

use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CashMovementService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CashMovement::query()->latest('id');

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

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function createManual(array $data, ?int $actorId = null): CashMovement
    {
        $type = (string) $data['type'];
        $direction = (string) $data['direction'];
        $this->ensureManualDirectionValid($type, $direction);

        return CashMovement::query()->create([
            'type' => $type,
            'direction' => $direction,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'movement_date' => $data['movement_date'] ?? Carbon::now(),
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
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
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expense->id,
            'reference' => $expense->reference,
            'movement_date' => $expense->expense_date?->startOfDay() ?? Carbon::now(),
            'description' => $expense->description,
            'notes' => $expense->notes,
            'created_by' => $actorId ?? $expense->created_by,
        ];

        if ($movement instanceof CashMovement) {
            if ($movement->trashed()) {
                $movement->restore();
            }

            $movement->fill($payload);
            $movement->save();

            return $movement->refresh();
        }

        return CashMovement::query()->create($payload);
    }

    public function softDeleteExpenseMovement(Expense $expense): void
    {
        CashMovement::query()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->delete();
    }

    public function recordInvoicePayment(InvoicePayment $payment, ?int $actorId = null): CashMovement
    {
        return CashMovement::query()->create([
            'type' => CashMovement::TYPE_INVOICE_PAYMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->method,
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $payment->id,
            'reference' => $payment->reference,
            'movement_date' => $payment->paid_at ?? Carbon::now(),
            'description' => 'Invoice payment received',
            'notes' => $payment->notes,
            'created_by' => $actorId ?? $payment->created_by,
        ]);
    }

    public function recordSecurityDepositDeduction(
        SecurityDepositTransaction $transaction,
        ?int $actorId = null
    ): CashMovement {
        return CashMovement::query()->create([
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_DEDUCTION,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => round((float) $transaction->amount, 2),
            'method' => null,
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
            'reference_id' => $transaction->id,
            'reference' => $transaction->reason,
            'movement_date' => Carbon::now(),
            'description' => 'Security deposit deduction',
            'notes' => $transaction->notes,
            'created_by' => $actorId ?? $transaction->created_by,
        ]);
    }

    public function recordSupplierPayment(SupplierPayment $payment, ?int $actorId = null): CashMovement
    {
        return CashMovement::query()->create([
            'type' => CashMovement::TYPE_SUPPLIER_PAYMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->method,
            'reference_type' => CashMovement::REFERENCE_SUPPLIER_PAYMENT,
            'reference_id' => $payment->id,
            'reference' => $payment->reference,
            'movement_date' => $payment->paid_at ?? Carbon::now(),
            'description' => 'Supplier payment',
            'notes' => $payment->notes,
            'created_by' => $actorId ?? $payment->created_by,
        ]);
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
}
