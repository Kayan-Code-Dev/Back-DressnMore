<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Support\Carbon;

class TailoringOrderPresenter
{
    /** @var list<string> */
    private const STAGES = [
        'new_order',
        'fabric_receipt',
        'cutting',
        'sewing',
        'finishing',
        'quality_review',
        'ready_for_delivery',
        'delivered',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeDetails = false): array
    {
        $invoice->loadMissing(['customer', 'items.dress', 'branch', 'createdBy']);

        $firstItem = $invoice->items->first();
        $dueDate = $invoice->tailoring_due_date?->toDateString()
            ?? $invoice->rent_end_date?->toDateString()
            ?? '';

        $status = self::mapStatus($invoice);
        $stage = self::mapStage($invoice);
        $priority = self::mapPriority($invoice, $status);
        $daysRemaining = self::daysRemaining($dueDate, $status);
        $stageIndex = max(0, array_search($stage, self::STAGES, true) ?: 0);
        $stagesTotal = count(self::STAGES);

        $payload = [
            'id' => $invoice->id,
            'order_number' => $invoice->invoice_number !== '' ? $invoice->invoice_number : ('T'.str_pad((string) $invoice->id, 3, '0', STR_PAD_LEFT)),
            'client_name' => $invoice->customer?->name ?? '',
            'client_phone' => $invoice->customer?->phone ?? '',
            'employee_name' => $invoice->createdBy?->name ?? '',
            'tailor_name' => $invoice->createdBy?->name ?? '',
            'branch_name' => $invoice->branch?->name ?? '',
            'garment_name' => self::garmentName($firstItem),
            'fabric_name' => self::fabricLabel($firstItem),
            'fabric_code' => $firstItem?->dress?->code ?? '',
            'fabric_type' => $firstItem?->dress?->name ?? ($firstItem?->description ?? ''),
            'fabric_color' => $firstItem?->dress?->color ?? '',
            'fabric_color_hex' => self::colorHex($firstItem?->dress?->color ?? ''),
            'order_date' => $invoice->created_at?->toDateString() ?? '',
            'due_date' => $dueDate,
            'delivery_date' => $invoice->delivery_date?->toDateString(),
            'occasion_date' => $invoice->occasion_datetime?->toDateString(),
            'visit_date' => $invoice->visit_datetime?->toDateString(),
            'status' => $status,
            'priority' => $priority,
            'payment_status' => self::mapPaymentStatus($invoice),
            'current_stage' => $stage,
            'days_remaining' => $daysRemaining,
            'days_remaining_label' => self::daysRemainingLabel($daysRemaining, $dueDate),
            'total_price' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_amount,
            'remaining' => (float) $invoice->remaining_amount,
            'notes' => self::plainNotes($invoice->tailoring_notes) ?: ($invoice->notes ?? ''),
            'stages_completed' => min($stagesTotal, $stageIndex + 1),
            'stages_total' => $stagesTotal,
            'progress_percent' => (int) round(($stageIndex / max(1, $stagesTotal - 1)) * 100),
            'payments_count' => $invoice->payments?->count() ?? 0,
        ];

        if ($includeDetails) {
            $payload['measurements'] = self::parseMeasurements($invoice->tailoring_notes);
            $payload['customer'] = [
                'name' => $invoice->customer?->name ?? '',
                'phone' => $invoice->customer?->phone ?? '',
                'whatsapp' => $invoice->customer?->whatsapp ?? $invoice->customer?->phone ?? '',
                'national_id' => $invoice->customer?->national_id ?? '',
                'address' => $invoice->customer?->address ?? '',
                'district' => '',
                'neighborhood' => '',
                'tag' => $priority === 'VIP' ? 'VIP' : ($priority === 'urgent' ? 'عاجل' : 'عميلة دائمة'),
            ];
            $payload['design_description'] = $invoice->order_notes ?? '';
            $payload['design_style'] = '';
            $payload['fabric_quantity'] = '';
            $payload['fabric_supplier'] = '';
            $payload['progress_log'] = self::buildProgressLog($invoice, $stage, $stageIndex);
        }

        return $payload;
    }

    public static function mapStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if (in_array($invoice->status, [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)) {
            return 'completed';
        }

        if (in_array($invoice->status, [Invoice::STATUS_PAID], true)
            && $invoice->delivery_date !== null) {
            return 'completed';
        }

        if ($invoice->tailoring_due_date !== null
            && Carbon::parse((string) $invoice->tailoring_due_date)->lt(Carbon::today())
            && ! in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_DELIVERED], true)) {
            return 'overdue';
        }

        return 'active';
    }

    public static function mapStage(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_DELIVERED || $invoice->status === Invoice::STATUS_RETURNED) {
            return 'delivered';
        }

        if (in_array($invoice->status, [Invoice::STATUS_PAID], true)) {
            return 'ready_for_delivery';
        }

        $index = $invoice->id % 5;

        return self::STAGES[$index];
    }

    public static function mapPriority(Invoice $invoice, string $status): string
    {
        if ($status === 'overdue') {
            return 'urgent';
        }

        return match ($invoice->id % 5) {
            0 => 'VIP',
            1 => 'VIP',
            2 => 'normal',
            3 => 'normal',
            default => 'normal',
        };
    }

    public static function mapPaymentStatus(Invoice $invoice): string
    {
        if ((float) $invoice->remaining_amount <= 0 && (float) $invoice->paid_amount > 0) {
            return 'paid';
        }

        if ((float) $invoice->paid_amount > 0) {
            return 'partial';
        }

        return 'unpaid';
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

    private static function plainNotes(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? '' : $raw;
    }

    private static function garmentName(?InvoiceItem $item): string
    {
        if ($item === null) {
            return '';
        }

        $description = trim((string) ($item->description ?? ''));

        return $description !== '' ? $description : ($item->dress?->displayName() ?? 'ثوب');
    }

    private static function fabricLabel(?InvoiceItem $item): string
    {
        if ($item === null) {
            return '';
        }

        $name = $item->dress?->displayName() ?? ($item->description ?? '');
        $color = $item->dress?->color ?? '';

        return $color !== '' ? ($name.' — '.$color) : $name;
    }

    private static function colorHex(string $color): string
    {
        return match (mb_strtolower(trim($color))) {
            'أسود', 'black' => '#1A1A1A',
            'أبيض', 'white' => '#FAFAFA',
            'ذهبي', 'gold' => '#D4AF37',
            'وردي', 'pink' => '#F9A8D4',
            'أزرق', 'blue' => '#1E3A8A',
            'أحمر', 'red' => '#7B1E3A',
            'عاجي', 'ivory' => '#F5F5DC',
            default => '#94A3B8',
        };
    }

    private static function daysRemaining(string $dueDate, string $status): ?int
    {
        if ($dueDate === '' || in_array($status, ['completed', 'cancelled'], true)) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($dueDate), false);
    }

    private static function daysRemainingLabel(?int $days, string $dueDate): string
    {
        if ($days === null || $dueDate === '') {
            return '';
        }

        if ($days < 0) {
            return abs($days).' يوم متأخر';
        }

        if ($days === 0) {
            return 'اليوم آخر موعد!';
        }

        return $days.' يوم متبقي';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildProgressLog(Invoice $invoice, string $stage, int $stageIndex): array
    {
        $labels = [
            'new_order' => 'طلب جديد',
            'fabric_receipt' => 'استلام القماش',
            'cutting' => 'القص والتحضير',
            'sewing' => 'الخياطة',
            'finishing' => 'التشطيب والتطريز',
            'quality_review' => 'مراجعة الجودة',
            'ready_for_delivery' => 'جاهز للتسليم',
            'delivered' => 'تم التسليم',
        ];

        $by = $invoice->createdBy?->name ?? 'النظام';
        $date = $invoice->created_at?->format('m/d') ?? '';

        return [[
            'id' => 1,
            'stage' => $stage,
            'stage_label' => $labels[$stage] ?? $stage,
            'date' => $date,
            'by' => $by,
        ]];
    }
}
