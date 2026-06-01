<?php

namespace App\Support\Tenant;

use App\Enums\RentalReturnCondition;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RentalReturnSettlementCalculator
{
    public static function dressStatusForCondition(string $condition): string
    {
        return match ($condition) {
            RentalReturnCondition::DAMAGED->value => Dress::STATUS_MAINTENANCE,
            RentalReturnCondition::LOST->value => Dress::STATUS_UNAVAILABLE,
            default => Dress::STATUS_AVAILABLE,
        };
    }

    public static function calculateLateDays(Invoice $invoice, Carbon $actualReturnDate): int
    {
        if ($invoice->rent_end_date === null) {
            return 0;
        }

        $dueDate = Carbon::parse((string) $invoice->rent_end_date)->startOfDay();

        return max(0, (int) $dueDate->diffInDays($actualReturnDate->copy()->startOfDay(), false));
    }

    public static function suggestedLateFee(Invoice $invoice, int $lateDays): float
    {
        if ($lateDays <= 0) {
            return 0.0;
        }

        $penaltyPerDay = InvoiceReturnPresenter::penaltyPerDay($invoice);

        return round($lateDays * $penaltyPerDay, 2);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function buildSettlementFigures(Invoice $invoice, array $input): array
    {
        $actualReturnDate = Carbon::parse((string) $input['returned_at'])->startOfDay();
        $condition = (string) ($input['condition'] ?? RentalReturnCondition::GOOD->value);
        $lateDays = self::calculateLateDays($invoice, $actualReturnDate);

        $lateFee = round((float) ($input['late_fee'] ?? self::suggestedLateFee($invoice, $lateDays)), 2);
        $damageFee = round((float) ($input['damage_fee'] ?? 0), 2);
        $cleaningFee = round((float) ($input['cleaning_fee'] ?? 0), 2);
        $otherFee = round((float) ($input['other_fee'] ?? 0), 2);

        foreach (['late_fee' => $lateFee, 'damage_fee' => $damageFee, 'cleaning_fee' => $cleaningFee, 'other_fee' => $otherFee] as $field => $amount) {
            if ($amount < 0) {
                throw ValidationException::withMessages([
                    $field => ['يجب ألا تكون الرسوم سالبة'],
                ]);
            }
        }

        $totalFees = round($lateFee + $damageFee + $cleaningFee + $otherFee, 2);
        $depositAmount = round((float) ($invoice->security_deposit ?? 0), 2);
        $depositPaidAmount = round((float) ($invoice->deposit_paid_amount ?? 0), 2);
        $maxRefundable = $depositPaidAmount;

        $depositRefundAmount = array_key_exists('deposit_refund_amount', $input)
            ? round((float) $input['deposit_refund_amount'], 2)
            : max(0, round($depositPaidAmount - min($totalFees, $depositPaidAmount), 2));

        if ($depositRefundAmount < 0) {
            throw ValidationException::withMessages([
                'deposit_refund_amount' => ['مبلغ استرداد التأمين يجب ألا يكون سالباً'],
            ]);
        }

        if ($depositRefundAmount > $maxRefundable + 0.009) {
            throw ValidationException::withMessages([
                'deposit_refund_amount' => ['مبلغ استرداد التأمين يتجاوز التأمين المدفوع'],
            ]);
        }

        $depositWithheldAmount = round(min($totalFees, max(0, $depositPaidAmount - $depositRefundAmount)), 2);
        $additionalAmountDue = round(max(0, $totalFees - $depositWithheldAmount), 2);
        $settlementTotal = round($totalFees + $depositRefundAmount, 2);

        return [
            'expected_return_date' => $invoice->rent_end_date?->toDateString(),
            'actual_return_date' => $actualReturnDate->toDateString(),
            'condition' => $condition,
            'late_days' => $lateDays,
            'late_fee' => $lateFee,
            'damage_fee' => $damageFee,
            'cleaning_fee' => $cleaningFee,
            'other_fee' => $otherFee,
            'total_fees' => $totalFees,
            'deposit_amount' => $depositAmount,
            'deposit_paid_amount' => $depositPaidAmount,
            'max_refundable_deposit' => $maxRefundable,
            'suggested_late_fee' => self::suggestedLateFee($invoice, $lateDays),
            'suggested_deposit_refund_amount' => max(0, round($depositPaidAmount - min($totalFees, $depositPaidAmount), 2)),
            'suggested_deposit_withheld_amount' => round(min($totalFees, $depositPaidAmount), 2),
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_withheld_amount' => $depositWithheldAmount,
            'additional_amount_due' => $additionalAmountDue,
            'settlement_total' => $settlementTotal,
            'dress_status_after_return' => self::dressStatusForCondition($condition),
        ];
    }
}
