<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;
use Illuminate\Support\Carbon;

class InvoiceDeliveryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice): array
    {
        $base = RentalOrderPresenter::fromInvoice($invoice);
        $deliveryStatus = self::mapDeliveryStatus($invoice);

        return array_merge($base, [
            'delivery_status' => $deliveryStatus,
            'delay_days' => self::delayDays($invoice, $deliveryStatus),
        ]);
    }

    public static function mapDeliveryStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($invoice->status === Invoice::STATUS_RETURNED) {
            return 'returned';
        }

        if ($invoice->status === Invoice::STATUS_DELIVERED) {
            $today = Carbon::today();
            $rentEnd = $invoice->rent_end_date !== null
                ? Carbon::parse((string) $invoice->rent_end_date)->startOfDay()
                : null;
            $occasion = $invoice->occasion_datetime !== null
                ? Carbon::parse((string) $invoice->occasion_datetime)->startOfDay()
                : null;

            if ($rentEnd !== null && $rentEnd->lt($today)) {
                return 'late';
            }

            if ($occasion !== null && $occasion->gt($today)) {
                return 'received';
            }

            return 'delivered';
        }

        if (in_array($invoice->status, [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ], true)) {
            return 'waiting';
        }

        return 'waiting';
    }

    public static function delayDays(Invoice $invoice, ?string $deliveryStatus = null): int
    {
        $deliveryStatus ??= self::mapDeliveryStatus($invoice);

        if ($deliveryStatus !== 'late' || $invoice->rent_end_date === null) {
            return 0;
        }

        return max(0, (int) Carbon::parse((string) $invoice->rent_end_date)->startOfDay()->diffInDays(Carbon::today(), false));
    }
}
