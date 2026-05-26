<?php

namespace App\Services\Tenant;

use App\Enums\SecurityDepositStatus;
use App\Models\Tenant\Dress;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\SecurityDepositTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityDepositService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly CashMovementService $cashMovementService
    ) {}

    public function addDeduction(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        $this->ensureDeductionAllowed($invoice);

        $amount = round((float) $data['amount'], 2);
        $balance = $this->remainingBalance($invoice);

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => ['Deduction amount exceeds remaining security deposit balance'],
            ]);
        }

        /** @var Invoice $updated */
        $updated = DB::connection('tenant')->transaction(function () use ($invoice, $data, $amount, $actorId): Invoice {
            $transaction = SecurityDepositTransaction::query()->create([
                'invoice_id' => $invoice->id,
                'type' => SecurityDepositTransaction::TYPE_DEDUCTED,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->cashMovementService->recordSecurityDepositDeduction($transaction, $actorId);

            $remainingBalance = $this->remainingBalance($invoice->refresh());

            $invoice->security_deposit_status = $remainingBalance <= 0
                ? SecurityDepositStatus::FULLY_DEDUCTED->value
                : SecurityDepositStatus::PARTIALLY_DEDUCTED->value;
            $invoice->save();

            if ($this->isDamageOrRepairReason((string) ($data['reason'] ?? ''))) {
                foreach ($invoice->items()->whereNotNull('dress_id')->with('dress')->get() as $item) {
                    $dress = $item->dress;
                    if (! $dress instanceof Dress) {
                        continue;
                    }

                    if ($dress->status !== Dress::STATUS_MAINTENANCE) {
                        $dress->status = Dress::STATUS_MAINTENANCE;
                        $dress->save();

                        $this->inventoryService->recordMovement(
                            dress: $dress,
                            type: InventoryMovement::TYPE_MAINTENANCE,
                            quantity: max(1, (int) $item->quantity),
                            reason: 'Security deposit deduction due to damage/repair',
                            referenceType: Invoice::class,
                            referenceId: $invoice->id,
                            notes: $data['notes'] ?? null,
                            createdBy: $actorId,
                        );
                    }
                }
            }

            return $invoice->refresh();
        });

        return $updated->load([
            'items.dress.category',
            'items.dress.subcategory',
            'payments',
            'securityDepositTransactions',
        ]);
    }

    public function paginateTransactions(Invoice $invoice, int $perPage = 15): LengthAwarePaginator
    {
        return $invoice->securityDepositTransactions()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function remainingBalance(Invoice $invoice): float
    {
        $depositAmount = round((float) ($invoice->security_deposit ?? 0), 2);
        $deducted = round((float) $invoice->securityDepositTransactions()
            ->where('type', SecurityDepositTransaction::TYPE_DEDUCTED)
            ->sum('amount'), 2);
        $refunded = round((float) $invoice->securityDepositTransactions()
            ->where('type', SecurityDepositTransaction::TYPE_REFUNDED)
            ->sum('amount'), 2);

        return max(0, round($depositAmount - $deducted - $refunded, 2));
    }

    private function ensureDeductionAllowed(Invoice $invoice): void
    {
        if ($invoice->type !== Invoice::TYPE_RENT) {
            throw ValidationException::withMessages([
                'invoice' => ['Only rent invoices can have security deposit deductions'],
            ]);
        }

        if ((float) ($invoice->security_deposit ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'security_deposit' => ['Invoice has no security deposit'],
            ]);
        }
    }

    private function isDamageOrRepairReason(string $reason): bool
    {
        $normalized = mb_strtolower(trim($reason));
        if ($normalized === '') {
            return false;
        }

        foreach (['damage', 'repair', 'tear', 'broken'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
