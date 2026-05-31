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
        $invoice->loadMissing(['customer', 'branch', 'createdBy', 'items.dress']);

        $customer = $invoice->customer;
        $status = self::mapStatus($invoice);
        $paymentStatus = self::mapPaymentStatus($invoice);

        $payload = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? '',
            'client_name' => $customer?->name ?? '',
            'client_phone' => $customer?->phone ?? '',
            'customer' => [
                'name' => $customer?->name ?? '',
                'national_id' => $customer?->national_id ?? '',
                'phone' => $customer?->phone ?? '',
                'whatsapp' => $customer?->whatsapp ?? '',
                'address' => $customer?->address ?? '',
            ],
            'employee_name' => $invoice->createdBy?->name ?? '',
            'branch_name' => $invoice->branch?->name ?? '',
            'invoice_date' => $invoice->created_at?->toDateString() ?? '',
            'visit_date' => $invoice->visit_datetime?->toDateString() ?? '',
            'delivery_date' => $invoice->delivery_date?->toDateString() ?? '',
            'event_date' => $invoice->occasion_datetime?->toDateString() ?? '',
            'return_date' => $invoice->rent_end_date?->toDateString() ?? '',
            'total_price' => (float) $invoice->total,
            'tax' => (float) $invoice->tax,
            'paid' => (float) $invoice->paid_amount,
            'remaining' => (float) $invoice->remaining_amount,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'items_count' => $invoice->items->count(),
            'items_preview' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'name' => $item->dress?->displayName() ?? ($item->description ?? ''),
            ])->values()->all(),
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

    public static function mapPaymentStatus(Invoice $invoice): string
    {
        $paid = (float) $invoice->paid_amount;
        $remaining = (float) $invoice->remaining_amount;

        if ($remaining <= 0 && $paid > 0) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }
}
