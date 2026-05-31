<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Support\Carbon;

class TailoringOrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeDetails = false): array
    {
        $invoice->loadMissing(['customer', 'items.dress']);

        $firstItem = $invoice->items->first();
        $dueDate = $invoice->tailoring_due_date?->toDateString()
            ?? $invoice->rent_end_date?->toDateString()
            ?? '';

        $payload = [
            'id' => $invoice->id,
            'client_name' => $invoice->customer?->name ?? '',
            'client_phone' => $invoice->customer?->phone ?? '',
            'employee_name' => '',
            'fabric_name' => $firstItem?->dress?->displayName() ?? ($firstItem?->description ?? ''),
            'fabric_code' => $firstItem?->dress?->code ?? '',
            'order_date' => $invoice->created_at?->toDateString() ?? '',
            'due_date' => $dueDate,
            'delivery_date' => $invoice->delivery_date?->toDateString(),
            'status' => self::mapStatus($invoice),
            'priority' => 'normal',
            'current_stage' => self::mapStage($invoice),
            'total_price' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_amount,
            'remaining' => (float) $invoice->remaining_amount,
            'notes' => $invoice->tailoring_notes ?? $invoice->notes,
        ];

        if ($includeDetails) {
            $payload['measurements'] = self::parseMeasurements($invoice->tailoring_notes);
        }

        return $payload;
    }

    public static function mapStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)) {
            return 'completed';
        }

        if ($invoice->tailoring_due_date !== null
            && Carbon::parse((string) $invoice->tailoring_due_date)->lt(Carbon::today())) {
            return 'overdue';
        }

        return 'active';
    }

    public static function mapStage(Invoice $invoice): string
    {
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED], true)) {
            return 'ready_for_delivery';
        }

        return 'sewing';
    }

    /**
     * @return list<array{id:int,label:string,value:string,unit:string}>
     */
    public static function parseMeasurements(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->values()
            ->map(fn ($row, $index): array => [
                'id' => (int) ($row['id'] ?? ($index + 1)),
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
                'unit' => (string) ($row['unit'] ?? 'cm'),
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $measurements
     */
    public static function encodeMeasurements(array $measurements): string
    {
        return json_encode(array_values($measurements), JSON_UNESCAPED_UNICODE);
    }
}
