<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;
use Illuminate\Support\Carbon;

class InvoiceReturnPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'branch', 'createdBy', 'items.dress', 'payments', 'deliveryRecords']);

        $base = RentalOrderPresenter::fromInvoice($invoice);
        $returnStatus = self::mapReturnStatus($invoice);
        $returnType = self::mapReturnType($invoice, $returnStatus);
        $delayDays = self::delayDays($invoice, $returnStatus);
        $penaltyPerDay = self::penaltyPerDay($invoice);
        $penaltyAmount = self::penaltyAmount($invoice, $returnStatus, $delayDays, $penaltyPerDay);
        $penaltyPaid = self::penaltyPaid($invoice, $penaltyAmount, $returnStatus);
        $penaltyDue = max(0, round($penaltyAmount - $penaltyPaid, 2));

        return array_merge($base, [
            'return_status' => $returnStatus,
            'return_type' => $returnType,
            'delay_days' => $delayDays,
            'actual_return_date' => $invoice->return_date?->toDateString() ?? '',
            'penalty_per_day' => $penaltyPerDay,
            'penalty_amount' => $penaltyAmount,
            'penalty_paid' => $penaltyPaid,
            'penalty_due' => $penaltyDue,
            'product_condition' => $returnStatus === 'returned' ? 'good' : '',
            'return_note' => $invoice->order_notes ?? $invoice->notes ?? '',
        ]);
    }

    public static function mapReturnStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_RETURNED) {
            return 'returned';
        }

        if ($invoice->status === Invoice::STATUS_DELIVERED
            && $invoice->rent_end_date !== null
            && Carbon::parse((string) $invoice->rent_end_date)->startOfDay()->lt(Carbon::today())) {
            return 'late';
        }

        return 'waiting';
    }

    public static function mapReturnType(Invoice $invoice, ?string $returnStatus = null): string
    {
        $returnStatus ??= self::mapReturnStatus($invoice);

        return match ($returnStatus) {
            'late' => 'late',
            'returned' => 'instant',
            default => 'scheduled',
        };
    }

    public static function delayDays(Invoice $invoice, ?string $returnStatus = null): int
    {
        $returnStatus ??= self::mapReturnStatus($invoice);

        if ($invoice->rent_end_date === null) {
            return 0;
        }

        $dueDate = Carbon::parse((string) $invoice->rent_end_date)->startOfDay();

        if ($returnStatus === 'returned' && $invoice->return_date !== null) {
            $returnDate = Carbon::parse((string) $invoice->return_date)->startOfDay();

            return max(0, (int) $dueDate->diffInDays($returnDate, false));
        }

        if ($returnStatus === 'late') {
            return max(0, (int) $dueDate->diffInDays(Carbon::today(), false));
        }

        return 0;
    }

    public static function penaltyPerDay(Invoice $invoice): float
    {
        $days = max(1, (int) ($invoice->days_of_rent ?? 1));

        return round(((float) $invoice->total / $days) * 0.15, 2);
    }

    public static function penaltyAmount(
        Invoice $invoice,
        ?string $returnStatus = null,
        ?int $delayDays = null,
        ?float $penaltyPerDay = null,
    ): float {
        $returnStatus ??= self::mapReturnStatus($invoice);
        $delayDays ??= self::delayDays($invoice, $returnStatus);
        $penaltyPerDay ??= self::penaltyPerDay($invoice);

        if ($delayDays <= 0) {
            return 0.0;
        }

        return round($delayDays * $penaltyPerDay, 2);
    }

    public static function penaltyPaid(Invoice $invoice, float $penaltyAmount, ?string $returnStatus = null): float
    {
        $returnStatus ??= self::mapReturnStatus($invoice);

        if ($penaltyAmount <= 0) {
            return 0.0;
        }

        if ($returnStatus === 'returned') {
            return min($penaltyAmount, round($penaltyAmount * 0.35, 2));
        }

        return 0.0;
    }
}
