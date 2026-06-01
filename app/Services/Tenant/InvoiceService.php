<?php

namespace App\Services\Tenant;

use App\Enums\SecurityDepositStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\InvoicePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['branch', 'items.dress.category', 'items.dress.subcategory'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->whereRaw('LOWER(invoice_number) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $this->applyExactFilter($query, 'customer_id', $filters['customer_id'] ?? ($filters['client_id'] ?? null));
        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        $this->applyExactFilter($query, 'type', $filters['type'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): Invoice
    {
        $items = $this->normalizeItems($data['items'] ?? []);
        $summary = $this->calculateSummary(
            items: $items,
            discount: (float) ($data['discount'] ?? 0),
            discountType: (string) ($data['discount_type'] ?? 'fixed'),
            discountValue: $data['discount_value'] ?? null,
            tax: (float) ($data['tax'] ?? 0),
        );

        $status = (string) ($data['status'] ?? Invoice::STATUS_DRAFT);
        $invoiceType = (string) $data['type'];

        if ($invoiceType === Invoice::TYPE_RENT && $status === Invoice::STATUS_CONFIRMED) {
            $this->assertRentAvailability(
                items: $items,
                rentStartDate: $data['rent_start_date'] ?? null,
                rentEndDate: $data['rent_end_date'] ?? null,
                ignoreInvoiceId: null,
            );
        }

        /** @var Invoice $invoice */
        $invoice = DB::connection('tenant')->transaction(function () use ($data, $items, $summary, $status, $actorId): Invoice {
            $invoice = Invoice::query()->create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'type' => $data['type'],
                'status' => $status,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'discount_type' => $summary['discount_type'],
                'discount_value' => $summary['discount_value'],
                'tax' => $summary['tax'],
                'total' => $summary['total'],
                'paid_amount' => 0,
                'remaining_amount' => $summary['total'],
                'rent_start_date' => $data['rent_start_date'] ?? null,
                'rent_end_date' => $data['rent_end_date'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'security_deposit' => $data['security_deposit'] ?? null,
                'security_deposit_status' => $data['security_deposit_status']
                    ?? ((float) ($data['security_deposit'] ?? 0) > 0 ? SecurityDepositStatus::NONE->value : null),
                'tailoring_due_date' => $data['tailoring_due_date'] ?? null,
                'visit_datetime' => $data['visit_datetime'] ?? null,
                'occasion_datetime' => $data['occasion_datetime'] ?? null,
                'days_of_rent' => $data['days_of_rent'] ?? null,
                'tailoring_notes' => $data['tailoring_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'order_notes' => $data['order_notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->replaceItems($invoice, $items);

            if (isset($data['initial_payment']) && is_array($data['initial_payment'])) {
                $initialPayment = $data['initial_payment'];
                InvoicePayment::query()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $this->money((float) ($initialPayment['amount'] ?? 0)),
                    'method' => $initialPayment['method'] ?? null,
                    'reference' => $initialPayment['reference'] ?? null,
                    'paid_at' => $initialPayment['paid_at'] ?? Carbon::now(),
                    'notes' => $initialPayment['notes'] ?? null,
                    'created_by' => $actorId,
                ]);
            }

            return $this->refreshFinancials($invoice, $status);
        });

        return $invoice->load(['branch', 'items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function findOrFail(int $invoiceId): Invoice
    {
        return Invoice::query()
            ->with(['branch', 'items.dress.category', 'items.dress.subcategory', 'payments'])
            ->findOrFail($invoiceId);
    }

    public function update(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        $allowCancelledUpdate = (bool) ($data['allow_cancelled_update'] ?? false);

        if ($invoice->status === Invoice::STATUS_CANCELLED && ! $allowCancelledUpdate) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoice cannot be updated'],
            ]);
        }

        $newStatus = (string) ($data['status'] ?? $invoice->status);
        $newType = (string) ($data['type'] ?? $invoice->type);
        $newItems = array_key_exists('items', $data)
            ? $this->normalizeItems($data['items'])
            : $invoice->items()->get()->map(fn (InvoiceItem $item): array => [
                'dress_id' => $item->dress_id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->all();

        if ($newType === Invoice::TYPE_RENT && $newStatus === Invoice::STATUS_CONFIRMED) {
            $this->assertRentAvailability(
                items: $newItems,
                rentStartDate: $data['rent_start_date'] ?? $invoice->rent_start_date?->toDateString(),
                rentEndDate: $data['rent_end_date'] ?? $invoice->rent_end_date?->toDateString(),
                ignoreInvoiceId: $invoice->id,
            );
        }

        $summary = $this->calculateSummary(
            items: $newItems,
            discount: (float) ($data['discount'] ?? $invoice->discount),
            discountType: (string) ($data['discount_type'] ?? ($invoice->discount_type ?? 'fixed')),
            discountValue: $data['discount_value'] ?? $invoice->discount_value,
            tax: (float) ($data['tax'] ?? $invoice->tax),
        );

        /** @var Invoice $updatedInvoice */
        $updatedInvoice = DB::connection('tenant')->transaction(function () use ($invoice, $data, $newItems, $summary, $newStatus, $newType, $actorId): Invoice {
            $invoice->fill([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'branch_id' => $data['branch_id'] ?? $invoice->branch_id,
                'type' => $newType,
                'status' => $newStatus,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'discount_type' => $summary['discount_type'],
                'discount_value' => $summary['discount_value'],
                'tax' => $summary['tax'],
                'total' => $summary['total'],
                'rent_start_date' => $data['rent_start_date'] ?? $invoice->rent_start_date,
                'rent_end_date' => $data['rent_end_date'] ?? $invoice->rent_end_date,
                'delivery_date' => $data['delivery_date'] ?? $invoice->delivery_date,
                'return_date' => $data['return_date'] ?? $invoice->return_date,
                'security_deposit' => $data['security_deposit'] ?? $invoice->security_deposit,
                'security_deposit_status' => $data['security_deposit_status']
                    ?? ((float) ($data['security_deposit'] ?? $invoice->security_deposit) > 0
                        ? ($invoice->security_deposit_status ?: SecurityDepositStatus::NONE->value)
                        : null),
                'tailoring_due_date' => $data['tailoring_due_date'] ?? $invoice->tailoring_due_date,
                'visit_datetime' => $data['visit_datetime'] ?? $invoice->visit_datetime,
                'occasion_datetime' => $data['occasion_datetime'] ?? $invoice->occasion_datetime,
                'days_of_rent' => $data['days_of_rent'] ?? $invoice->days_of_rent,
                'tailoring_notes' => $data['tailoring_notes'] ?? $invoice->tailoring_notes,
                'notes' => $data['notes'] ?? $invoice->notes,
                'order_notes' => $data['order_notes'] ?? $invoice->order_notes,
                'created_by' => $invoice->created_by ?? $actorId,
            ]);
            $invoice->save();

            if (array_key_exists('items', $data)) {
                $this->replaceItems($invoice, $newItems);
            }

            return $this->refreshFinancials($invoice, $newStatus);
        });

        return $updatedInvoice->refresh()->load(['branch', 'items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice already cancelled'],
            ]);
        }

        if (in_array($invoice->status, [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Delivered or returned invoices cannot be cancelled'],
            ]);
        }

        $invoice->status = Invoice::STATUS_CANCELLED;
        $invoice->save();

        return $this->refreshFinancials($invoice->refresh(), Invoice::STATUS_CANCELLED)
            ->load(['branch', 'items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function refreshFinancials(Invoice $invoice, ?string $preferredStatus = null): Invoice
    {
        $paidAmount = $this->money((float) $invoice->payments()
            ->where(function (Builder $builder): void {
                $builder->where('status', InvoicePayment::STATUS_PAID)
                    ->orWhereNull('status');
            })
            ->sum('amount'));
        $total = $this->money((float) $invoice->total);
        $remainingAmount = $this->money(max(0, $total - $paidAmount));

        $status = $this->resolveStatus(
            currentStatus: $preferredStatus ?? (string) $invoice->status,
            total: $total,
            paidAmount: $paidAmount,
            remainingAmount: $remainingAmount,
        );

        $invoice->fill([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
        ]);
        $invoice->save();

        return $invoice;
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (Invoice $invoice): array {
            return [
                $invoice->id,
                $invoice->invoice_number,
                $invoice->customer_id,
                $invoice->branch_id,
                $invoice->type,
                $invoice->status,
                $invoice->total,
                $invoice->paid_amount,
                $invoice->remaining_amount,
                $invoice->delivery_date?->toDateString(),
                $invoice->created_at?->toDateTimeString(),
            ];
        }, $rows);
    }

    private function assertRentAvailability(
        array $items,
        mixed $rentStartDate,
        mixed $rentEndDate,
        ?int $ignoreInvoiceId
    ): void {
        $dressIds = collect($items)
            ->pluck('dress_id')
            ->filter(fn (mixed $id): bool => $id !== null)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($dressIds === []) {
            return;
        }

        $startDate = $rentStartDate !== null ? (string) $rentStartDate : null;
        $endDate = $rentEndDate !== null ? (string) $rentEndDate : null;

        if ($startDate === null || $endDate === null) {
            return;
        }

        $blockingStatuses = [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_DELIVERED,
            Invoice::STATUS_RETURNED,
        ];

        $exists = Invoice::query()
            ->where('type', Invoice::TYPE_RENT)
            ->whereIn('status', $blockingStatuses)
            ->when($ignoreInvoiceId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreInvoiceId))
            ->whereDate('rent_start_date', '<=', $endDate)
            ->whereDate('rent_end_date', '>=', $startDate)
            ->whereHas('items', function (Builder $query) use ($dressIds): void {
                $query->whereIn('dress_id', $dressIds);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rent_period' => ['الفستان غير متاح خلال فترة التأجير المحددة.'],
            ]);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = $this->money((float) ($item['unit_price'] ?? 0));
                $total = $this->money($quantity * $unitPrice);

                return [
                    'dress_id' => isset($item['dress_id']) ? (int) $item['dress_id'] : null,
                    'item_type' => $item['item_type'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array{subtotal:float,discount:float,discount_type:string,discount_value:float,tax:float,total:float}
     */
    private function calculateSummary(
        array $items,
        float $discount,
        string $discountType,
        mixed $discountValue,
        float $tax
    ): array {
        $subtotal = $this->money((float) collect($items)->sum(fn (array $item): float => (float) $item['total']));
        $normalizedDiscountType = in_array($discountType, ['fixed', 'percentage'], true) ? $discountType : 'fixed';
        $normalizedDiscountValue = $this->money(max(0, (float) ($discountValue ?? $discount)));
        $normalizedDiscount = $normalizedDiscountType === 'percentage'
            ? $this->money(($subtotal * $normalizedDiscountValue) / 100)
            : $normalizedDiscountValue;
        $normalizedTax = $this->money(max(0, $tax));
        $total = $this->money(max(0, $subtotal - $normalizedDiscount + $normalizedTax));

        return [
            'subtotal' => $subtotal,
            'discount' => $normalizedDiscount,
            'discount_type' => $normalizedDiscountType,
            'discount_value' => $normalizedDiscountValue,
            'tax' => $normalizedTax,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $items
     */
    private function replaceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            $invoice->items()->create($item);
        }
    }

    private function resolveStatus(
        string $currentStatus,
        float $total,
        float $paidAmount,
        float $remainingAmount
    ): string {
        if (in_array($currentStatus, [
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_DELIVERED,
            Invoice::STATUS_RETURNED,
        ], true)) {
            return $currentStatus;
        }

        if ($paidAmount <= 0) {
            if (in_array($currentStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true)) {
                return Invoice::STATUS_CONFIRMED;
            }

            return $currentStatus;
        }

        if ($remainingAmount <= 0 || ($total > 0 && $paidAmount >= $total)) {
            return Invoice::STATUS_PAID;
        }

        return Invoice::STATUS_PARTIALLY_PAID;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $sequence = Invoice::withTrashed()
            ->where('invoice_number', 'like', $prefix.'-%')
            ->count() + 1;

        for ($i = 0; $i < 1000; $i++) {
            $invoiceNumber = sprintf('%s-%04d', $prefix, $sequence + $i);

            if (! Invoice::withTrashed()->where('invoice_number', $invoiceNumber)->exists()) {
                return $invoiceNumber;
            }
        }

        return sprintf('%s-%s', $prefix, strtoupper(substr(md5((string) microtime(true)), 0, 6)));
    }

    private function applyExactFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return;
        }

        $query->where($column, $normalized);
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }
}
