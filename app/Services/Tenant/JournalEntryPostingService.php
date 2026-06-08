<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\RentalReturnSettlement;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class JournalEntryPostingService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function postFromInvoicePayment(InvoicePayment $payment, ?int $actorId = null): ?JournalEntry
    {
        if ($payment->status !== InvoicePayment::STATUS_PAID) {
            return null;
        }

        $payment->loadMissing('invoice');
        $invoice = $payment->invoice;
        if (! $invoice instanceof Invoice) {
            return null;
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $revenueCode = match ($invoice->type) {
            Invoice::TYPE_SELL => '4100',
            Invoice::TYPE_TAILORING => '4100',
            default => '4000',
        };

        return $this->safePost([
            'entry_date' => ($payment->paid_at ?? now())->toDateString(),
            'source_type' => JournalEntry::SOURCE_PAYMENT,
            'source_id' => $payment->id,
            'reference_number' => $payment->reference ?: $invoice->invoice_number,
            'description' => 'قيد دفعة فاتورة '.$invoice->invoice_number,
            'branch_id' => $invoice->branch_id,
        ], [
            ['code' => '1000', 'debit' => $amount, 'credit' => 0, 'description' => 'تحصيل نقدي'],
            ['code' => $revenueCode, 'debit' => 0, 'credit' => $amount, 'description' => 'إيراد'],
        ], $actorId);
    }

    public function postFromExpense(Expense $expense, ?int $actorId = null): ?JournalEntry
    {
        if ($expense->status !== Expense::STATUS_PAID) {
            return null;
        }

        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return $this->safePost([
            'entry_date' => ($expense->expense_date ?? now())->toDateString(),
            'source_type' => JournalEntry::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'reference_number' => $expense->reference_number ?: $expense->reference,
            'description' => $expense->description ?: 'قيد مصروف',
            'branch_id' => $expense->branch_id,
        ], [
            ['code' => '5000', 'debit' => $amount, 'credit' => 0, 'description' => 'مصروف'],
            ['code' => '1000', 'debit' => 0, 'credit' => $amount, 'description' => 'صرف من الصندوق'],
        ], $actorId);
    }

    public function postFromCashMovement(CashMovement $movement, ?int $actorId = null): ?JournalEntry
    {
        if ($movement->is_reversed) {
            return null;
        }

        if (in_array($movement->reference_type, [
            CashMovement::REFERENCE_EXPENSE,
            CashMovement::REFERENCE_INVOICE_PAYMENT,
            CashMovement::REFERENCE_SUPPLIER_PAYMENT,
            CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
        ], true)) {
            return null;
        }

        $amount = round((float) $movement->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $isIn = $movement->direction === CashMovement::DIRECTION_IN;
        $lines = $isIn
            ? [
                ['code' => '1000', 'debit' => $amount, 'credit' => 0],
                ['code' => '4100', 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['code' => '5000', 'debit' => $amount, 'credit' => 0],
                ['code' => '1000', 'debit' => 0, 'credit' => $amount],
            ];

        $movement->loadMissing('cashbox');

        return $this->safePost([
            'entry_date' => ($movement->movement_date ?? now())->toDateString(),
            'source_type' => JournalEntry::SOURCE_CASH_MOVEMENT,
            'source_id' => $movement->id,
            'reference_number' => $movement->reference,
            'description' => $movement->description ?: 'قيد حركة خزنة',
            'branch_id' => $movement->cashbox?->branch_id,
        ], $lines, $actorId);
    }

    public function postFromSecurityDepositCollection(
        SecurityDepositTransaction $transaction,
        ?int $actorId = null,
    ): ?JournalEntry {
        if ($transaction->type !== SecurityDepositTransaction::TYPE_COLLECTED) {
            return null;
        }

        $amount = round((float) $transaction->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $transaction->loadMissing('invoice');
        $invoice = $transaction->invoice;

        return $this->safePost([
            'entry_date' => now()->toDateString(),
            'source_type' => JournalEntry::SOURCE_SECURITY_DEPOSIT_COLLECTION,
            'source_id' => $transaction->id,
            'reference_number' => $invoice?->invoice_number,
            'description' => 'قيد استلام تأمين فاتورة '.($invoice?->invoice_number ?? ''),
            'branch_id' => $invoice?->branch_id,
        ], [
            ['code' => '1000', 'debit' => $amount, 'credit' => 0, 'description' => 'تحصيل تأمين'],
            ['code' => '2100', 'debit' => 0, 'credit' => $amount, 'description' => 'وديعة تأمين مستحقة'],
        ], $actorId);
    }

    public function postFromRentalReturnSettlement(
        RentalReturnSettlement $settlement,
        ?int $actorId = null,
    ): ?JournalEntry {
        $withheld = round((float) $settlement->deposit_withheld_amount, 2);
        $refund = round((float) $settlement->deposit_refund_amount, 2);
        $additional = round((float) $settlement->additional_amount_due, 2);
        $lateFee = round((float) $settlement->late_fee, 2);
        $damageFee = round((float) $settlement->damage_fee, 2);
        $cleaningFee = round((float) $settlement->cleaning_fee, 2);
        $otherFee = round((float) $settlement->other_fee, 2);

        if ($withheld <= 0 && $refund <= 0 && $additional <= 0) {
            return null;
        }

        $lines = [];

        if ($withheld > 0) {
            $lines[] = ['code' => '2100', 'debit' => $withheld, 'credit' => 0, 'description' => 'خصم تأمين مقابل رسوم'];
        }

        if ($refund > 0) {
            $lines[] = ['code' => '2100', 'debit' => $refund, 'credit' => 0, 'description' => 'استرداد تأمين'];
            $lines[] = ['code' => '1000', 'debit' => 0, 'credit' => $refund, 'description' => 'صرف استرداد تأمين'];
        }

        if ($additional > 0) {
            $lines[] = ['code' => '1200', 'debit' => $additional, 'credit' => 0, 'description' => 'مستحقات عميل - رسوم إرجاع'];
        }

        foreach ([
            ['amount' => $lateFee, 'code' => '4200', 'label' => 'غرامة تأخير'],
            ['amount' => $damageFee, 'code' => '4210', 'label' => 'أضرار'],
            ['amount' => $cleaningFee, 'code' => '4220', 'label' => 'تنظيف'],
            ['amount' => $otherFee, 'code' => '4220', 'label' => 'رسوم أخرى'],
        ] as $fee) {
            if ($fee['amount'] > 0) {
                $lines[] = ['code' => $fee['code'], 'debit' => 0, 'credit' => $fee['amount'], 'description' => $fee['label']];
            }
        }

        $settlement->loadMissing('invoice');
        $invoice = $settlement->invoice;

        return $this->safePost([
            'entry_date' => ($settlement->actual_return_date ?? now())->toDateString(),
            'source_type' => JournalEntry::SOURCE_RENTAL_RETURN_SETTLEMENT,
            'source_id' => $settlement->id,
            'reference_number' => $invoice?->invoice_number,
            'description' => 'قيد تسوية إرجاع إيجار '.($invoice?->invoice_number ?? ''),
            'branch_id' => $settlement->branch_id ?? $invoice?->branch_id,
        ], $lines, $actorId);
    }

    public function postFromSupplierPayment(SupplierPayment $payment, ?int $actorId = null): ?JournalEntry
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $payment->loadMissing(['purchaseOrder', 'cashbox']);
        $branchId = $payment->branch_id ?? $payment->cashbox?->branch_id ?? $payment->purchaseOrder?->branch_id;
        $description = $payment->purchaseOrder?->purchase_order_number !== null
            ? 'قيد دفعة مورد لطلبية '.$payment->purchaseOrder->purchase_order_number
            : 'قيد دفعة مورد';

        return $this->safePost([
            'entry_date' => ($payment->paid_at ?? now())->toDateString(),
            'source_type' => JournalEntry::SOURCE_SUPPLIER_PAYMENT,
            'source_id' => $payment->id,
            'reference_number' => $payment->reference,
            'description' => $description,
            'branch_id' => $branchId,
        ], [
            ['code' => '2000', 'debit' => $amount, 'credit' => 0, 'description' => 'سداد مورد'],
            ['code' => '1000', 'debit' => 0, 'credit' => $amount, 'description' => 'صرف نقدي'],
        ], $actorId);
    }

    public function cancelBySource(string $sourceType, int $sourceId, ?int $actorId = null): void
    {
        try {
            $this->journalEntryService->cancelBySource($sourceType, $sourceId, $actorId);
        } catch (\Throwable $exception) {
            Log::warning('journal_entry_cancel_failed', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{code: string, debit?: float, credit?: float, description?: string|null}>  $lines
     */
    private function safePost(array $header, array $lines, ?int $actorId): ?JournalEntry
    {
        try {
            return $this->journalEntryService->createFromSource(
                $header,
                $this->mapLines($lines),
                $actorId,
            );
        } catch (\Throwable $exception) {
            Log::warning('journal_entry_post_failed', [
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<array{code: string, debit?: float, credit?: float, description?: string|null}>  $lines
     * @return list<array<string, mixed>>
     */
    private function mapLines(array $lines): array
    {
        return array_map(function (array $line): array {
            $account = Account::query()->where('code', $line['code'])->firstOrFail();

            return [
                'account_id' => $account->id,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
            ];
        }, $lines);
    }
}
