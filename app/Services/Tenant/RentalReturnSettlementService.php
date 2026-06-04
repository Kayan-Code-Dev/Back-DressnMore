<?php

namespace App\Services\Tenant;

use App\Enums\RentalReturnCondition;
use App\Enums\RentalReturnSettlementStatus;
use App\Enums\SecurityDepositStatus;
use App\Models\Tenant\DeliveryRecord;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\RentalReturnSettlement;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Support\Tenant\RentalReturnSettlementCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalReturnSettlementService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceDeliveryService $invoiceDeliveryService,
        private readonly JournalEntryPostingService $journalEntryPostingService,
        private readonly CashMovementService $cashMovementService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(Invoice $invoice, array $input): array
    {
        $this->ensurePreviewable($invoice, $input);
        $figures = RentalReturnSettlementCalculator::buildSettlementFigures($invoice, $input);

        return $this->presentPayload($invoice, $figures);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function settle(Invoice $invoice, array $input, ?int $actorId = null): RentalReturnSettlement
    {
        $this->ensureSettleable($invoice, $input);
        $figures = RentalReturnSettlementCalculator::buildSettlementFigures($invoice, $input);

        /** @var RentalReturnSettlement $settlement */
        $settlement = DB::connection('tenant')->transaction(function () use ($invoice, $input, $figures, $actorId): RentalReturnSettlement {
            $settlement = RentalReturnSettlement::query()->create([
                'tenant_id' => $this->tenantContext->id(),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'branch_id' => $invoice->branch_id,
                'expected_return_date' => $figures['expected_return_date'],
                'actual_return_date' => $figures['actual_return_date'],
                'condition' => $figures['condition'],
                'late_days' => $figures['late_days'],
                'late_fee' => $figures['late_fee'],
                'damage_fee' => $figures['damage_fee'],
                'cleaning_fee' => $figures['cleaning_fee'],
                'other_fee' => $figures['other_fee'],
                'total_fees' => $figures['total_fees'],
                'deposit_amount' => $figures['deposit_amount'],
                'deposit_paid_amount' => $figures['deposit_paid_amount'],
                'deposit_refund_amount' => $figures['deposit_refund_amount'],
                'deposit_withheld_amount' => $figures['deposit_withheld_amount'],
                'additional_amount_due' => $figures['additional_amount_due'],
                'settlement_total' => $figures['settlement_total'],
                'status' => RentalReturnSettlementStatus::SETTLED->value,
                'notes' => $input['notes'] ?? null,
                'created_by' => $actorId,
                'settled_by' => $actorId,
                'settled_at' => now(),
            ]);

            $this->invoiceDeliveryService->returnRentInvoice($invoice->refresh(), [
                'returned_at' => $figures['actual_return_date'],
                'dress_status_after_return' => $figures['dress_status_after_return'],
                'notes' => $input['notes'] ?? null,
            ], $actorId);

            $this->recordDepositMovements($invoice->refresh(), $settlement, $actorId);
            $this->updateDepositStatus($invoice->refresh(), $figures);

            return $settlement;
        });

        $journalEntry = $this->journalEntryPostingService->postFromRentalReturnSettlement($settlement->refresh(), $actorId);
        if ($journalEntry !== null) {
            $settlement->journal_entry_id = $journalEntry->id;
            $settlement->save();
        }

        return $settlement->refresh()->load(['invoice.customer', 'journalEntry']);
    }

    /**
     * Backward compatibility: record zero-fee settlement when legacy return endpoint is used.
     *
     * @param  array<string, mixed>  $input
     */
    public function ensureLegacySettlement(Invoice $invoice, array $input, ?int $actorId = null): ?RentalReturnSettlement
    {
        if ($this->hasBlockingSettlement($invoice->id)) {
            return RentalReturnSettlement::query()
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', RentalReturnSettlementStatus::blockingStatuses())
                ->first();
        }

        $returnedAt = isset($input['returned_at'])
            ? Carbon::parse((string) $input['returned_at'])
            : Carbon::now();

        $condition = RentalReturnCondition::GOOD->value;
        $dressStatus = (string) ($input['dress_status_after_return'] ?? '');
        if ($dressStatus === Dress::STATUS_MAINTENANCE) {
            $condition = RentalReturnCondition::DAMAGED->value;
        } elseif ($dressStatus === Dress::STATUS_UNAVAILABLE) {
            $condition = RentalReturnCondition::LOST->value;
        }

        $figures = RentalReturnSettlementCalculator::buildSettlementFigures($invoice->refresh(), [
            'returned_at' => $returnedAt->toDateString(),
            'condition' => $condition,
            'late_fee' => 0,
            'damage_fee' => 0,
            'cleaning_fee' => 0,
            'other_fee' => 0,
            'deposit_refund_amount' => 0,
        ]);

        return RentalReturnSettlement::query()->create([
            'tenant_id' => $this->tenantContext->id(),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'branch_id' => $invoice->branch_id,
            'expected_return_date' => $figures['expected_return_date'],
            'actual_return_date' => $figures['actual_return_date'],
            'condition' => $figures['condition'],
            'late_days' => $figures['late_days'],
            'late_fee' => 0,
            'damage_fee' => 0,
            'cleaning_fee' => 0,
            'other_fee' => 0,
            'total_fees' => 0,
            'deposit_amount' => $figures['deposit_amount'],
            'deposit_paid_amount' => $figures['deposit_paid_amount'],
            'deposit_refund_amount' => 0,
            'deposit_withheld_amount' => 0,
            'additional_amount_due' => 0,
            'settlement_total' => 0,
            'status' => RentalReturnSettlementStatus::SETTLED->value,
            'notes' => $input['notes'] ?? 'Legacy return without financial settlement',
            'created_by' => $actorId,
            'settled_by' => $actorId,
            'settled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $figures
     * @return array<string, mixed>
     */
    public function presentPayload(Invoice $invoice, array $figures): array
    {
        $invoice->loadMissing(['customer', 'branch', 'items.dress']);

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'type' => $invoice->type,
                'status' => $invoice->status,
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount,
            ],
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
            ] : null,
            'expected_return_date' => $figures['expected_return_date'],
            'actual_return_date' => $figures['actual_return_date'],
            'condition' => $figures['condition'],
            'late_days' => $figures['late_days'],
            'suggested_late_fee' => $figures['suggested_late_fee'],
            'late_fee' => $figures['late_fee'],
            'damage_fee' => $figures['damage_fee'],
            'cleaning_fee' => $figures['cleaning_fee'],
            'other_fee' => $figures['other_fee'],
            'total_fees' => $figures['total_fees'],
            'deposit_amount' => $figures['deposit_amount'],
            'deposit_paid_amount' => $figures['deposit_paid_amount'],
            'max_refundable_deposit' => $figures['max_refundable_deposit'],
            'suggested_deposit_refund_amount' => $figures['suggested_deposit_refund_amount'],
            'suggested_deposit_withheld_amount' => $figures['suggested_deposit_withheld_amount'],
            'deposit_refund_amount' => $figures['deposit_refund_amount'],
            'deposit_withheld_amount' => $figures['deposit_withheld_amount'],
            'additional_amount_due' => $figures['additional_amount_due'],
            'settlement_total' => $figures['settlement_total'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ensurePreviewable(Invoice $invoice, array $input): void
    {
        $this->ensureRentDelivered($invoice);
        $this->validateReturnTiming($invoice, $input);

        if (! isset($input['returned_at'])) {
            throw ValidationException::withMessages([
                'returned_at' => ['تاريخ الإرجاع مطلوب'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ensureSettleable(Invoice $invoice, array $input): void
    {
        $this->ensurePreviewable($invoice, $input);

        if ($this->hasBlockingSettlement($invoice->id)) {
            throw ValidationException::withMessages([
                'invoice' => ['تم تسوية إرجاع هذه الفاتورة مسبقاً'],
            ]);
        }

        if ($invoice->status === Invoice::STATUS_RETURNED) {
            throw ValidationException::withMessages([
                'invoice' => ['الفاتورة مُرجعة بالفعل'],
            ]);
        }
    }

    private function ensureRentDelivered(Invoice $invoice): void
    {
        if ($invoice->type !== Invoice::TYPE_RENT) {
            throw ValidationException::withMessages([
                'invoice' => ['التسوية متاحة فقط لفواتير الإيجار'],
            ]);
        }

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['لا يمكن تسوية فاتورة ملغاة'],
            ]);
        }

        $wasDelivered = $invoice->deliveryRecords()
            ->where('type', DeliveryRecord::TYPE_DELIVERED)
            ->exists();

        if (! $wasDelivered && $invoice->status !== Invoice::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'invoice' => ['يجب تسليم الفستان قبل تسوية الإرجاع'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validateReturnTiming(Invoice $invoice, array $input): void
    {
        if (! isset($input['returned_at'])) {
            return;
        }

        $returnedAt = Carbon::parse((string) $input['returned_at'])->startOfDay();

        if ($invoice->delivery_date !== null) {
            $deliveredAt = Carbon::parse((string) $invoice->delivery_date)->startOfDay();
            if ($returnedAt->lt($deliveredAt)) {
                throw ValidationException::withMessages([
                    'returned_at' => ['تاريخ الإرجاع لا يمكن أن يكون قبل تاريخ التسليم'],
                ]);
            }
        }

        $condition = (string) ($input['condition'] ?? RentalReturnCondition::GOOD->value);
        if (! in_array($condition, RentalReturnCondition::values(), true)) {
            throw ValidationException::withMessages([
                'condition' => ['حالة الفستان غير صالحة'],
            ]);
        }
    }

    private function hasBlockingSettlement(int $invoiceId): bool
    {
        return RentalReturnSettlement::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', RentalReturnSettlementStatus::blockingStatuses())
            ->exists();
    }

    private function recordDepositMovements(
        Invoice $invoice,
        RentalReturnSettlement $settlement,
        ?int $actorId,
    ): void {
        $withheld = round((float) $settlement->deposit_withheld_amount, 2);
        $refund = round((float) $settlement->deposit_refund_amount, 2);

        if ($withheld > 0) {
            SecurityDepositTransaction::query()->create([
                'invoice_id' => $invoice->id,
                'type' => SecurityDepositTransaction::TYPE_DEDUCTED,
                'amount' => $withheld,
                'reason' => 'rental_return_settlement',
                'notes' => $settlement->notes,
                'created_by' => $actorId,
            ]);
        }

        if ($refund > 0) {
            $transaction = SecurityDepositTransaction::query()->create([
                'invoice_id' => $invoice->id,
                'type' => SecurityDepositTransaction::TYPE_REFUNDED,
                'amount' => $refund,
                'reason' => 'rental_return_settlement',
                'notes' => $settlement->notes,
                'created_by' => $actorId,
            ]);

            $this->cashMovementService->recordSecurityDepositRefund($transaction, $actorId);
        }
    }

    /**
     * @param  array<string, mixed>  $figures
     */
    private function updateDepositStatus(Invoice $invoice, array $figures): void
    {
        $depositPaid = round((float) $figures['deposit_paid_amount'], 2);
        if ($depositPaid <= 0) {
            return;
        }

        $refund = round((float) $figures['deposit_refund_amount'], 2);
        $withheld = round((float) $figures['deposit_withheld_amount'], 2);

        if ($refund >= $depositPaid - 0.009 && $withheld <= 0.009) {
            $invoice->security_deposit_status = SecurityDepositStatus::REFUNDED->value;
        } elseif ($withheld >= $depositPaid - 0.009) {
            $invoice->security_deposit_status = SecurityDepositStatus::FULLY_DEDUCTED->value;
        } elseif ($withheld > 0 || $refund > 0) {
            $invoice->security_deposit_status = SecurityDepositStatus::PARTIALLY_DEDUCTED->value;
        }

        $invoice->save();
    }
}
