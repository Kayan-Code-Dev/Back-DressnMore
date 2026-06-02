<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;

class SaleInvoicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeItems = false, bool $includeDetails = false): array
    {
        $invoice->loadMissing(['customer', 'branch', 'createdBy', 'items.dress', 'payments']);

        $customer = $invoice->customer;
        $branch = $invoice->branch;
        $paymentStatus = RentalOrderPresenter::mapPaymentStatus($invoice);
        $invoiceStatus = self::mapInvoiceStatus($invoice);
        $total = (float) $invoice->total;
        $paid = (float) $invoice->paid_amount;
        $remaining = (float) $invoice->remaining_amount;
        $collectedPercent = $total > 0 ? (int) round(($paid / $total) * 100) : 0;
        $plannedReturn = $invoice->return_date?->toDateString()
            ?? $invoice->rent_end_date?->toDateString()
            ?? '';

        $payload = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? '',
            'branch_id' => $invoice->branch_id,
            'status' => $invoice->status,
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
            'branch_name' => $branch?->name ?? '',
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'phone' => $branch->phone ?? '',
                'address' => $branch->address ?? '',
                'logo_url' => $branch->image ?? '',
            ] : null,
            'sale_date' => $invoice->created_at?->toDateString() ?? '',
            'invoice_date' => $invoice->created_at?->toDateString() ?? '',
            'delivery_date' => $invoice->delivery_date?->toDateString() ?? '',
            'event_date' => $invoice->occasion_datetime?->toDateString() ?? '',
            'return_date' => $plannedReturn,
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
            'items_count' => $invoice->items->count(),
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

        if ($includeDetails) {
            $payload['payments'] = $invoice->payments?->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method ?? 'cash',
                'paid_at' => $payment->paid_at?->toDateString() ?? '',
                'notes' => $payment->notes,
            ])->values()->all() ?? [];
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
