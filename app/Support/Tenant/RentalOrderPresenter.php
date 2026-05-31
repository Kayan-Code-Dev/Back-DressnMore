<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Support\Carbon;

class RentalOrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeDetails = false): array
    {
        $invoice->loadMissing(['customer', 'items.dress']);

        $payload = [
            'id' => $invoice->id,
            'client_name' => $invoice->customer?->name ?? '',
            'client_phone' => $invoice->customer?->phone ?? '',
            'employee_name' => '',
            'visit_date' => $invoice->visit_datetime?->toDateString() ?? '',
            'delivery_date' => $invoice->delivery_date?->toDateString() ?? '',
            'return_date' => $invoice->rent_end_date?->toDateString() ?? '',
            'total_price' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_amount,
            'remaining' => (float) $invoice->remaining_amount,
            'status' => self::mapStatus($invoice),
            'items_count' => $invoice->items->count(),
            'notes' => $invoice->notes,
        ];

        if ($includeDetails) {
            $payload['items'] = $invoice->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'cloth_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'cloth_code' => $item->dress?->code ?? '',
                'size' => $item->dress?->size ?? '',
                'color' => $item->dress?->color ?? '',
                'rental_price' => (float) $item->unit_price,
                'return_date' => $invoice->rent_end_date?->toDateString() ?? '',
            ])->values()->all();

            $payload['payments'] = $invoice->payments?->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method ?? 'cash',
                'paid_at' => $payment->paid_at?->toDateString() ?? '',
                'notes' => $payment->notes,
            ])->values()->all() ?? [];

            $payload['custodies'] = [];
        }

        return $payload;
    }

    public static function mapStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($invoice->status === Invoice::STATUS_RETURNED) {
            return 'returned';
        }

        if ($invoice->status === Invoice::STATUS_DELIVERED) {
            if ($invoice->rent_end_date !== null
                && Carbon::parse((string) $invoice->rent_end_date)->lt(Carbon::today())) {
                return 'overdue';
            }

            return 'active';
        }

        if (in_array($invoice->status, [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ], true)) {
            return 'active';
        }

        return 'pending';
    }
}
