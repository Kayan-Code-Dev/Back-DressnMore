<?php

namespace App\Support\Tenant;

use App\Enums\TailoringPriority;
use App\Enums\TailoringProductionStage;
use App\Enums\TailoringProductionStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\TailoringStageHistory;
use Illuminate\Support\Carbon;

class TailoringOrderPresenter
{
    /** @var list<string> */
    public const STAGES = [
        'new_order',
        'measurements_taken',
        'fabric_cutting',
        'sewing',
        'first_fitting',
        'adjustments',
        'final_fitting',
        'ready_for_delivery',
        'delivered',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromInvoice(Invoice $invoice, bool $includeDetails = false): array
    {
        $invoice->loadMissing(['customer', 'items.dress', 'branch', 'createdBy', 'assignedTailor', 'payments']);

        $firstItem = $invoice->items->first();
        $dueDate = $invoice->tailoring_due_date?->toDateString()
            ?? $invoice->rent_end_date?->toDateString()
            ?? '';

        $status = self::mapStatus($invoice);
        $stage = self::resolveStage($invoice);
        $productionStatus = self::resolveProductionStatus($invoice);
        $priority = self::resolvePriority($invoice, $status);
        $daysRemaining = self::daysRemaining($dueDate, $status);
        $stageIndex = max(0, array_search($stage, self::STAGES, true) ?: 0);
        $stagesTotal = count(self::STAGES);

        $stageEnum = TailoringProductionStage::tryFrom($stage);
        $statusEnum = TailoringProductionStatus::tryFrom($productionStatus);
        $priorityEnum = TailoringPriority::tryFrom($priority);

        $payload = [
            'id' => $invoice->id,
            'order_number' => $invoice->invoice_number !== '' ? $invoice->invoice_number : ('T'.str_pad((string) $invoice->id, 3, '0', STR_PAD_LEFT)),
            'client_name' => $invoice->customer?->name ?? '',
            'client_phone' => $invoice->customer?->phone ?? '',
            'customer_id' => $invoice->customer_id,
            'branch_id' => $invoice->branch_id,
            'employee_name' => $invoice->createdBy?->name ?? '',
            'tailor_name' => $invoice->assignedTailor?->name ?? '',
            'assigned_tailor_id' => $invoice->assigned_tailor_id,
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
            'fitting_date' => $invoice->fitting_date?->toDateString(),
            'next_follow_up_date' => $invoice->next_follow_up_date?->toDateString(),
            'status' => $status,
            'priority' => $priority,
            'priority_label' => $priorityEnum?->labelAr() ?? $priority,
            'production_status' => $productionStatus,
            'production_status_label' => $statusEnum?->labelAr() ?? $productionStatus,
            'payment_status' => self::mapPaymentStatus($invoice),
            'current_stage' => $stage,
            'current_stage_label' => $stageEnum?->labelAr() ?? $stage,
            'days_remaining' => $daysRemaining,
            'days_remaining_label' => self::daysRemainingLabel($daysRemaining, $dueDate),
            'total_price' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_amount,
            'remaining' => (float) $invoice->remaining_amount,
            'notes' => self::plainNotes($invoice->tailoring_notes) ?: ($invoice->notes ?? ''),
            'design_notes' => $invoice->design_notes ?? ($invoice->order_notes ?? ''),
            'workshop_notes' => $invoice->workshop_notes ?? '',
            'started_at' => $invoice->tailoring_started_at?->toIso8601String(),
            'completed_at' => $invoice->tailoring_completed_at?->toIso8601String(),
            'cancelled_at' => $invoice->tailoring_cancelled_at?->toIso8601String(),
            'stages_completed' => min($stagesTotal, $stageIndex + 1),
            'stages_total' => $stagesTotal,
            'progress_percent' => (int) round(($stageIndex / max(1, $stagesTotal - 1)) * 100),
            'payments_count' => $invoice->payments?->count() ?? 0,
        ];

        if ($includeDetails) {
            $payload['measurements'] = self::resolveMeasurements($invoice);
            $payload['customer'] = [
                'name' => $invoice->customer?->name ?? '',
                'phone' => $invoice->customer?->phone ?? '',
                'whatsapp' => $invoice->customer?->whatsapp ?? $invoice->customer?->phone ?? '',
                'national_id' => $invoice->customer?->national_id ?? '',
                'address' => $invoice->customer?->address ?? '',
                'district' => '',
                'neighborhood' => '',
                'tag' => $priority === TailoringPriority::HIGH->value || $priority === TailoringPriority::URGENT->value
                    ? 'عاجل'
                    : 'عميلة دائمة',
            ];
            $payload['design_description'] = $invoice->design_notes ?? ($invoice->order_notes ?? '');
            $payload['design_style'] = '';
            $payload['fabric_quantity'] = '';
            $payload['fabric_supplier'] = '';
            $payload['progress_log'] = self::buildProgressLog($invoice);
            $payload['stage_history'] = self::buildStageHistory($invoice);
        }

        return $payload;
    }

    public static function resolveStage(Invoice $invoice): string
    {
        if ($invoice->production_stage !== null && $invoice->production_stage !== '') {
            return (string) $invoice->production_stage;
        }

        if ($invoice->status === Invoice::STATUS_DELIVERED || $invoice->status === Invoice::STATUS_RETURNED) {
            return TailoringProductionStage::DELIVERED->value;
        }

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return TailoringProductionStage::CANCELLED->value;
        }

        return TailoringProductionStage::NEW_ORDER->value;
    }

    public static function resolveProductionStatus(Invoice $invoice): string
    {
        if ($invoice->production_status !== null && $invoice->production_status !== '') {
            return (string) $invoice->production_status;
        }

        return TailoringProductionStatus::PENDING->value;
    }

    public static function resolvePriority(Invoice $invoice, string $listStatus): string
    {
        if ($invoice->priority !== null && $invoice->priority !== '') {
            return (string) $invoice->priority;
        }

        if ($listStatus === 'overdue') {
            return TailoringPriority::URGENT->value;
        }

        return TailoringPriority::NORMAL->value;
    }

    public static function mapStatus(Invoice $invoice): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED
            || $invoice->production_stage === TailoringProductionStage::CANCELLED->value) {
            return 'cancelled';
        }

        if (in_array($invoice->status, [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)
            || $invoice->production_stage === TailoringProductionStage::DELIVERED->value) {
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
    public static function resolveMeasurements(Invoice $invoice): array
    {
        if (is_array($invoice->tailoring_measurements) && $invoice->tailoring_measurements !== []) {
            return self::normalizeMeasurements($invoice->tailoring_measurements);
        }

        return self::parseMeasurements($invoice->tailoring_notes);
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

        return self::normalizeMeasurements($decoded);
    }

    /**
     * @param  list<array<string, mixed>>  $measurements
     * @return list<array{id:int,label:string,value:string,unit:string}>
     */
    public static function normalizeMeasurements(array $measurements): array
    {
        return collect($measurements)
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
    private static function buildProgressLog(Invoice $invoice): array
    {
        return self::buildStageHistory($invoice);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildStageHistory(Invoice $invoice): array
    {
        $histories = $invoice->relationLoaded('tailoringStageHistories')
            ? $invoice->tailoringStageHistories
            : $invoice->tailoringStageHistories()->with('changedByUser')->orderByDesc('changed_at')->get();

        if ($histories->isEmpty()) {
            $stage = self::resolveStage($invoice);

            return [[
                'id' => 0,
                'stage' => $stage,
                'stage_label' => TailoringProductionStage::tryFrom($stage)?->labelAr() ?? $stage,
                'date' => $invoice->created_at?->format('m/d') ?? '',
                'by' => $invoice->createdBy?->name ?? 'النظام',
            ]];
        }

        return $histories
            ->sortByDesc('changed_at')
            ->values()
            ->map(fn (TailoringStageHistory $row): array => [
                'id' => $row->id,
                'stage' => $row->to_stage,
                'stage_label' => TailoringProductionStage::tryFrom($row->to_stage)?->labelAr() ?? $row->to_stage,
                'from_stage' => $row->from_stage,
                'to_stage' => $row->to_stage,
                'date' => $row->changed_at?->format('m/d') ?? '',
                'changed_at' => $row->changed_at?->toIso8601String(),
                'by' => $row->changedByUser?->name ?? 'النظام',
                'notes' => $row->notes,
            ])
            ->all();
    }
}
