<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;

class SaleInvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeItems = false): array
    {
        $invoice->loadMissing(['customer', 'branch', 'createdBy', 'items.dress', 'payments']);

        $paymentStatus = RentalOrderPresenter::mapPaymentStatus($invoice);
        $invoiceStatus = self::mapInvoiceStatus($invoice);
        $total = (float) $invoice->total;
        $paid = (float) $invoice->paid_amount;
        $remaining = (float) $invoice->remaining_amount;
        $collectedPercent = $total > 0 ? (int) round(($paid / $total) * 100) : 0;

        $payload = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? '',
            'client_name' => $invoice->customer?->name ?? '',
            'client_phone' => $invoice->customer?->phone ?? '',
            'employee_name' => $invoice->createdBy?->name ?? '',
            'branch_name' => $invoice->branch?->name ?? '',
            'sale_date' => $invoice->created_at?->toDateString() ?? '',
            'payment_method' => $invoice->payments->first()?->method ?? 'cash',
            'subtotal' => (float) $invoice->subtotal,
            'discount' => (float) $invoice->discount,
            'tax' => (float) $invoice->tax,
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'collected_percent' => $collectedPercent,
            'payment_status' => $paymentStatus,
            'invoice_status' => $invoiceStatus,
            'notes' => $invoice->notes,
        ];

        if ($includeItems) {
            $payload['items'] = $invoice->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_name' => $item->dress?->displayName() ?? ($item->description ?? ''),
                'product_code' => $item->dress?->code ?? '',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->values()->all();
        }

        return $payload;
    }

    public static function mapInvoiceStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if (in_array($invoice->status, [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)) {
            return 'completed';
        }

        if ($invoice->status === Invoice::STATUS_DRAFT) {
            return 'pending';
        }

        if (in_array($invoice->status, [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ], true)) {
            return 'in_progress';
        }

        return 'pending';
    }
}
