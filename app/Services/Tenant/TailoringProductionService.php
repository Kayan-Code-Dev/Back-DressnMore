<?php

namespace App\Services\Tenant;

use App\Enums\TailoringPriority;
use App\Enums\TailoringProductionStage;
use App\Enums\TailoringProductionStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\TailoringStageHistory;
use App\Support\Tenant\TailoringOrderPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TailoringProductionService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $actorId = null): Invoice
    {
        $data['type'] = Invoice::TYPE_TAILORING;
        $data['production_stage'] = TailoringProductionStage::NEW_ORDER->value;
        $data['production_status'] = TailoringProductionStatus::PENDING->value;
        $data['priority'] = $data['priority'] ?? TailoringPriority::NORMAL->value;

        if (isset($data['measurements']) && is_array($data['measurements'])) {
            $data['tailoring_measurements'] = $data['measurements'];
            unset($data['measurements']);
        }

        if (isset($data['design_notes']) && ! isset($data['order_notes'])) {
            $data['order_notes'] = $data['design_notes'];
        }

        /** @var Invoice $invoice */
        $invoice = DB::connection('tenant')->transaction(function () use ($data, $actorId): Invoice {
            $invoice = $this->invoiceService->create($data, $actorId);

            $updates = $this->buildProductionDefaults($invoice, $data);
            if ($updates !== []) {
                $invoice->fill($updates)->save();
            }

            $this->recordHistory(
                invoice: $invoice->refresh(),
                fromStage: null,
                toStage: (string) $invoice->production_stage,
                fromStatus: null,
                toStatus: (string) $invoice->production_status,
                changedBy: $actorId,
                notes: 'تم إنشاء طلب التفصيل',
            );

            return $invoice;
        });

        return $invoice->refresh()->load(['customer', 'items.dress', 'payments', 'branch', 'createdBy', 'assignedTailor', 'tailoringStageHistories.changedByUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        $this->ensureTailoringInvoice($invoice);

        if (isset($data['measurements']) && is_array($data['measurements'])) {
            $data['tailoring_measurements'] = $data['measurements'];
            unset($data['measurements']);
        }

        if (isset($data['fitting_date'])) {
            $this->assertFittingDate($invoice, (string) $data['fitting_date']);
        }

        $invoiceData = collect($data)->only([
            'customer_id',
            'branch_id',
            'tailoring_due_date',
            'fitting_date',
            'next_follow_up_date',
            'priority',
            'assigned_tailor_id',
            'design_notes',
            'workshop_notes',
            'order_notes',
            'tailoring_notes',
            'visit_datetime',
            'occasion_datetime',
            'items',
            'status',
            'discount',
            'discount_type',
            'discount_value',
            'tax',
        ])->filter(fn ($value) => $value !== null)->all();

        if (isset($data['tailoring_measurements'])) {
            $invoice->tailoring_measurements = $data['tailoring_measurements'];
        }

        if ($invoiceData !== []) {
            $this->invoiceService->update($invoice, $invoiceData, $actorId);
            $invoice->refresh();
        }

        foreach (['priority', 'assigned_tailor_id', 'fitting_date', 'next_follow_up_date', 'design_notes', 'workshop_notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $invoice->{$field} = $data[$field];
            }
        }

        $invoice->save();

        return $invoice->refresh()->load(['customer', 'items.dress', 'payments', 'branch', 'createdBy', 'assignedTailor', 'tailoringStageHistories.changedByUser']);
    }

    /**
     * @param  array{to_stage: string, to_status?: string|null, notes?: string|null}  $payload
     */
    public function changeStage(Invoice $invoice, array $payload, ?int $actorId = null, bool $canOverride = false): Invoice
    {
        $this->ensureTailoringInvoice($invoice);

        if ($invoice->production_stage === TailoringProductionStage::CANCELLED->value
            || $invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['لا يمكن تغيير مرحلة طلب ملغي'],
            ]);
        }

        $toStage = TailoringProductionStage::from($payload['to_stage']);
        $toStatus = isset($payload['to_status']) && $payload['to_status'] !== null && $payload['to_status'] !== ''
            ? TailoringProductionStatus::from($payload['to_status'])
            : $this->defaultStatusForStage($toStage);

        $fromStage = TailoringProductionStage::tryFrom((string) $invoice->production_stage)
            ?? TailoringProductionStage::NEW_ORDER;
        $fromStatus = TailoringProductionStatus::tryFrom((string) $invoice->production_status)
            ?? TailoringProductionStatus::PENDING;

        if ($toStage->index() < $fromStage->index() && ! $canOverride) {
            throw ValidationException::withMessages([
                'to_stage' => ['لا يمكن الرجوع لمرحلة سابقة بدون صلاحية تجاوز المرحلة'],
            ]);
        }

        if ($toStage === TailoringProductionStage::READY_FOR_DELIVERY && ! $canOverride) {
            $measurements = TailoringOrderPresenter::resolveMeasurements($invoice);
            if ($measurements === []) {
                throw ValidationException::withMessages([
                    'to_stage' => ['يجب تسجيل المقاسات قبل وضع الطلب جاهزاً للتسليم'],
                ]);
            }
        }

        if ($toStage === TailoringProductionStage::DELIVERED) {
            throw ValidationException::withMessages([
                'to_stage' => ['استخدم مسار التسليم لتحديد الطلب كمُسلّم'],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $fromStage, $fromStatus, $toStage, $toStatus, $payload, $actorId): Invoice {
            $now = Carbon::now();

            if ($invoice->tailoring_started_at === null && $toStage !== TailoringProductionStage::NEW_ORDER) {
                $invoice->tailoring_started_at = $now;
            }

            if (in_array($toStage, [TailoringProductionStage::READY_FOR_DELIVERY, TailoringProductionStage::DELIVERED], true)) {
                $invoice->tailoring_completed_at = $now;
            }

            if ($toStage === TailoringProductionStage::CANCELLED) {
                $invoice->tailoring_cancelled_at = $now;
            }

            $invoice->production_stage = $toStage->value;
            $invoice->production_status = $toStatus->value;
            $invoice->save();

            $this->recordHistory(
                invoice: $invoice,
                fromStage: $fromStage->value,
                toStage: $toStage->value,
                fromStatus: $fromStatus->value,
                toStatus: $toStatus->value,
                changedBy: $actorId,
                notes: $payload['notes'] ?? null,
            );

            return $invoice->refresh()->load(['customer', 'items.dress', 'payments', 'branch', 'createdBy', 'assignedTailor', 'tailoringStageHistories.changedByUser']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stageHistory(Invoice $invoice): array
    {
        $this->ensureTailoringInvoice($invoice);

        return $invoice->tailoringStageHistories()
            ->with('changedByUser')
            ->orderByDesc('changed_at')
            ->get()
            ->map(fn (TailoringStageHistory $row): array => [
                'id' => $row->id,
                'from_stage' => $row->from_stage,
                'to_stage' => $row->to_stage,
                'from_stage_label' => $row->from_stage
                    ? (TailoringProductionStage::tryFrom($row->from_stage)?->labelAr() ?? $row->from_stage)
                    : null,
                'to_stage_label' => TailoringProductionStage::tryFrom($row->to_stage)?->labelAr() ?? $row->to_stage,
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'from_status_label' => $row->from_status
                    ? (TailoringProductionStatus::tryFrom($row->from_status)?->labelAr() ?? $row->from_status)
                    : null,
                'to_status_label' => TailoringProductionStatus::tryFrom($row->to_status ?? '')?->labelAr() ?? $row->to_status,
                'changed_by' => $row->changed_by,
                'changed_by_name' => $row->changedByUser?->name ?? '',
                'changed_at' => $row->changed_at?->toIso8601String(),
                'notes' => $row->notes,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function workshopBoard(array $filters = []): array
    {
        $orders = app(TailoringOrderService::class)
            ->listForBoard($filters);

        $columns = [];
        foreach (TailoringProductionStage::ordered() as $stage) {
            if ($stage === TailoringProductionStage::DELIVERED->value) {
                continue;
            }

            $enum = TailoringProductionStage::from($stage);
            $stageOrders = $orders->filter(fn (Invoice $invoice): bool => (string) $invoice->production_stage === $stage)->values();

            $columns[] = [
                'stage' => $stage,
                'label' => $enum->labelAr(),
                'count' => $stageOrders->count(),
                'orders' => $stageOrders
                    ->map(fn (Invoice $invoice): array => TailoringOrderPresenter::fromInvoice($invoice))
                    ->all(),
            ];
        }

        $cancelled = $orders->filter(
            fn (Invoice $invoice): bool => (string) $invoice->production_stage === TailoringProductionStage::CANCELLED->value
        );
        if ($cancelled->isNotEmpty()) {
            $columns[] = [
                'stage' => TailoringProductionStage::CANCELLED->value,
                'label' => TailoringProductionStage::CANCELLED->labelAr(),
                'count' => $cancelled->count(),
                'orders' => $cancelled
                    ->map(fn (Invoice $invoice): array => TailoringOrderPresenter::fromInvoice($invoice))
                    ->all(),
            ];
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function schedule(array $filters = []): array
    {
        $orders = app(TailoringOrderService::class)
            ->listForBoard($filters)
            ->filter(fn (Invoice $invoice): bool => $invoice->production_stage !== TailoringProductionStage::CANCELLED->value
                && $invoice->production_stage !== TailoringProductionStage::DELIVERED->value);

        $fitting = [];
        $delivery = [];
        $overdue = [];

        foreach ($orders as $invoice) {
            $card = TailoringOrderPresenter::fromInvoice($invoice);

            if ($invoice->fitting_date !== null) {
                $key = $invoice->fitting_date->toDateString();
                $fitting[$key] ??= ['date' => $key, 'orders' => []];
                $fitting[$key]['orders'][] = $card;
            }

            $dueDate = $invoice->tailoring_due_date?->toDateString()
                ?? $invoice->occasion_datetime?->toDateString();
            if ($dueDate !== null && $dueDate !== '') {
                $delivery[$dueDate] ??= ['date' => $dueDate, 'orders' => []];
                $delivery[$dueDate]['orders'][] = $card;
            }

            if ($invoice->next_follow_up_date !== null
                && $invoice->next_follow_up_date->lt(Carbon::today())) {
                $overdue[] = $card;
            } elseif ($invoice->fitting_date !== null
                && $invoice->fitting_date->lt(Carbon::today())
                && ! in_array($invoice->production_stage, [
                    TailoringProductionStage::READY_FOR_DELIVERY->value,
                    TailoringProductionStage::DELIVERED->value,
                ], true)) {
                $overdue[] = $card;
            }
        }

        ksort($fitting);
        ksort($delivery);

        return [
            'fitting_dates' => array_values($fitting),
            'delivery_dates' => array_values($delivery),
            'overdue' => $overdue,
        ];
    }

    public function syncFromInvoiceStatus(Invoice $invoice): void
    {
        if (! $invoice->isTailoring()) {
            return;
        }

        if ($invoice->status === Invoice::STATUS_DELIVERED
            && $invoice->production_stage !== TailoringProductionStage::DELIVERED->value) {
            $fromStage = (string) $invoice->production_stage;
            $fromStatus = (string) $invoice->production_status;
            $invoice->production_stage = TailoringProductionStage::DELIVERED->value;
            $invoice->production_status = TailoringProductionStatus::COMPLETED->value;
            $invoice->tailoring_completed_at ??= Carbon::now();
            $invoice->save();

            $this->recordHistory(
                invoice: $invoice,
                fromStage: $fromStage,
                toStage: TailoringProductionStage::DELIVERED->value,
                fromStatus: $fromStatus,
                toStatus: TailoringProductionStatus::COMPLETED->value,
                changedBy: null,
                notes: 'تم التسليم عبر مسار التسليم',
            );
        }

        if ($invoice->status === Invoice::STATUS_CANCELLED
            && $invoice->production_stage !== TailoringProductionStage::CANCELLED->value) {
            $fromStage = (string) $invoice->production_stage;
            $fromStatus = (string) $invoice->production_status;
            $invoice->production_stage = TailoringProductionStage::CANCELLED->value;
            $invoice->production_status = TailoringProductionStatus::CANCELLED->value;
            $invoice->tailoring_cancelled_at ??= Carbon::now();
            $invoice->save();

            $this->recordHistory(
                invoice: $invoice,
                fromStage: $fromStage,
                toStage: TailoringProductionStage::CANCELLED->value,
                fromStatus: $fromStatus,
                toStatus: TailoringProductionStatus::CANCELLED->value,
                changedBy: null,
                notes: 'تم الإلغاء',
            );
        }
    }

    private function ensureTailoringInvoice(Invoice $invoice): void
    {
        if (! $invoice->isTailoring()) {
            throw ValidationException::withMessages([
                'invoice' => ['هذه العملية متاحة فقط لفواتير التفصيل'],
            ]);
        }
    }

    private function assertFittingDate(Invoice $invoice, string $fittingDate): void
    {
        $invoiceDate = $invoice->created_at?->toDateString() ?? Carbon::today()->toDateString();
        if (Carbon::parse($fittingDate)->lt(Carbon::parse($invoiceDate))) {
            throw ValidationException::withMessages([
                'fitting_date' => ['تاريخ البروفة لا يمكن أن يكون قبل تاريخ الفاتورة'],
            ]);
        }
    }

    private function defaultStatusForStage(TailoringProductionStage $stage): TailoringProductionStatus
    {
        return match ($stage) {
            TailoringProductionStage::NEW_ORDER => TailoringProductionStatus::PENDING,
            TailoringProductionStage::READY_FOR_DELIVERY => TailoringProductionStatus::READY,
            TailoringProductionStage::DELIVERED => TailoringProductionStatus::COMPLETED,
            TailoringProductionStage::CANCELLED => TailoringProductionStatus::CANCELLED,
            TailoringProductionStage::FIRST_FITTING,
            TailoringProductionStage::FINAL_FITTING => TailoringProductionStatus::WAITING_CUSTOMER,
            default => TailoringProductionStatus::IN_PROGRESS,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildProductionDefaults(Invoice $invoice, array $data): array
    {
        $updates = [];

        foreach ([
            'production_stage' => TailoringProductionStage::NEW_ORDER->value,
            'production_status' => TailoringProductionStatus::PENDING->value,
            'priority' => TailoringPriority::NORMAL->value,
        ] as $field => $default) {
            if ($invoice->{$field} === null || $invoice->{$field} === '') {
                $updates[$field] = $data[$field] ?? $default;
            }
        }

        foreach (['fitting_date', 'next_follow_up_date', 'assigned_tailor_id', 'design_notes', 'workshop_notes', 'tailoring_measurements'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (isset($data['design_notes']) && ($invoice->design_notes === null || $invoice->design_notes === '')) {
            $updates['design_notes'] = $data['design_notes'];
        }

        return $updates;
    }

    private function recordHistory(
        Invoice $invoice,
        ?string $fromStage,
        string $toStage,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedBy,
        ?string $notes,
    ): void {
        TailoringStageHistory::query()->create([
            'invoice_id' => $invoice->id,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'changed_at' => Carbon::now(),
            'notes' => $notes,
        ]);
    }
}
