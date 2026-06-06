<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Dress;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly InventoryService $inventoryService,
    ) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'items', 'branch', 'category', 'subcategory'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(purchase_order_number) LIKE ?', [$wildcard])
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($wildcard): void {
                        $supplierQuery->whereRaw('LOWER(name) LIKE ?', [$wildcard]);
                    });
            });
        }

        $this->applyExactFilter($query, 'supplier_id', $filters['supplier_id'] ?? null);
        $this->applyExactFilter($query, 'branch_id', $filters['branch_id'] ?? null);
        $this->applyExactFilter($query, 'category_id', $filters['category_id'] ?? null);
        $this->applyExactFilter($query, 'subcategory_id', $filters['subcategory_id'] ?? null);
        $this->applyExactFilter($query, 'type', $filters['type'] ?? null);
        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
        if (($filters['is_returned'] ?? null) !== null && trim((string) $filters['is_returned']) !== '') {
            $query->where('is_returned', filter_var($filters['is_returned'], FILTER_VALIDATE_BOOLEAN));
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('order_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('order_date', '<=', $dateTo);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): PurchaseOrder
    {
        $items = $this->normalizeItems($data['items']);
        $summary = $this->calculateSummary(
            items: $items,
            discount: (float) ($data['discount'] ?? 0),
            tax: (float) ($data['tax'] ?? 0),
        );

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = DB::connection('tenant')->transaction(function () use ($data, $items, $summary, $actorId): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'purchase_order_number' => $this->generatePurchaseOrderNumber(),
                'status' => $data['status'] ?? PurchaseOrder::STATUS_DRAFT,
                'type' => $data['type'] ?? null,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'tax' => $summary['tax'],
                'total' => $summary['total'],
                'paid_amount' => 0,
                'remaining_amount' => $summary['total'],
                'order_date' => $data['order_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->replaceItems($purchaseOrder, $items);

            return $this->syncFinancials($purchaseOrder, (string) $purchaseOrder->status);
        });

        $this->supplierService->recalculateCurrentBalance($purchaseOrder->supplier()->firstOrFail());

        return $this->findOrFail($purchaseOrder->id);
    }

    public function findOrFail(int $purchaseOrderId): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'items', 'branch', 'category', 'subcategory'])
            ->findOrFail($purchaseOrderId);
    }

    public function update(PurchaseOrder $purchaseOrder, array $data, ?int $actorId = null): PurchaseOrder
    {
        $oldSupplierId = (int) $purchaseOrder->supplier_id;
        $items = $this->normalizeItems($data['items']);
        $summary = $this->calculateSummary(
            items: $items,
            discount: (float) ($data['discount'] ?? 0),
            tax: (float) ($data['tax'] ?? 0),
        );

        /** @var PurchaseOrder $updated */
        $updated = DB::connection('tenant')->transaction(function () use ($purchaseOrder, $data, $items, $summary, $actorId): PurchaseOrder {
            $purchaseOrder->fill([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'status' => $data['status'] ?? $purchaseOrder->status,
                'type' => $data['type'] ?? $purchaseOrder->type,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'tax' => $summary['tax'],
                'total' => $summary['total'],
                'order_date' => $data['order_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $purchaseOrder->created_by ?? $actorId,
            ]);
            $purchaseOrder->save();

            $this->replaceItems($purchaseOrder, $items);

            return $this->syncFinancials($purchaseOrder, (string) $purchaseOrder->status);
        });

        $this->supplierService->recalculateCurrentBalance($updated->supplier()->firstOrFail());

        if ($oldSupplierId !== (int) $updated->supplier_id) {
            $oldSupplier = $this->supplierService->findOrFail($oldSupplierId);
            $this->supplierService->recalculateCurrentBalance($oldSupplier);
        }

        return $this->findOrFail($updated->id);
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $supplier = $purchaseOrder->supplier()->first();

        $purchaseOrder->delete();

        if ($supplier !== null) {
            $this->supplierService->recalculateCurrentBalance($supplier);
        }
    }

    public function returnOrder(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if ($purchaseOrder->is_returned) {
            return $purchaseOrder->refresh();
        }

        $purchaseOrder->is_returned = true;
        $purchaseOrder->returned_at = $data['returned_at'] ?? now();
        $purchaseOrder->return_notes = $data['return_notes'] ?? null;
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PAID) {
            $purchaseOrder->status = PurchaseOrder::STATUS_CANCELLED;
        }
        $purchaseOrder->save();

        $this->supplierService->recalculateCurrentBalance($purchaseOrder->supplier()->firstOrFail());

        return $purchaseOrder->refresh()->load(['supplier', 'items', 'branch', 'category', 'subcategory']);
    }

    public function receiveOrder(PurchaseOrder $purchaseOrder, array $data = [], ?int $actorId = null): PurchaseOrder
    {
        if ($purchaseOrder->is_received) {
            return $this->findOrFail($purchaseOrder->id);
        }

        if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'purchase_order' => ['Cancelled purchase order cannot be received'],
            ]);
        }

        if ($purchaseOrder->is_returned) {
            throw ValidationException::withMessages([
                'purchase_order' => ['Returned purchase order cannot be received'],
            ]);
        }

        if ($purchaseOrder->branch_id === null) {
            throw ValidationException::withMessages([
                'branch_id' => ['Purchase order branch is required before receiving'],
            ]);
        }

        if ($purchaseOrder->category_id === null || $purchaseOrder->subcategory_id === null) {
            throw ValidationException::withMessages([
                'category_id' => ['Purchase order category and subcategory are required before receiving'],
            ]);
        }

        $purchaseOrder->loadMissing(['items', 'category', 'subcategory']);
        if ($purchaseOrder->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Purchase order must contain at least one item before receiving'],
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($purchaseOrder, $data, $actorId): void {
            foreach ($purchaseOrder->items as $item) {
                $quantity = (float) $item->quantity;
                $units = (int) round($quantity);
                if ($units < 1 || abs($quantity - $units) > 0.00001) {
                    throw ValidationException::withMessages([
                        'items' => [sprintf('Item [%s] quantity must be a whole number to receive into stock', $item->item_name)],
                    ]);
                }

                for ($unit = 1; $unit <= $units; $unit++) {
                    $dressCode = $this->generateReceivedDressCode(
                        purchaseOrder: $purchaseOrder,
                        itemId: (int) $item->id,
                        unitSequence: $unit,
                    );

                    $dress = Dress::query()->create([
                        'dress_category_id' => $purchaseOrder->category_id,
                        'dress_subcategory_id' => $purchaseOrder->subcategory_id,
                        'branch_id' => $purchaseOrder->branch_id,
                        'code' => $dressCode,
                        'name' => $this->buildReceivedDressName($dressCode, $purchaseOrder),
                        'description' => $item->description ?: $item->item_name,
                        'purchase_price' => $this->money((float) $item->unit_price),
                        'status' => Dress::STATUS_AVAILABLE,
                        'notes' => $data['receive_notes'] ?? $purchaseOrder->notes,
                    ]);

                    $this->inventoryService->recordMovement(
                        dress: $dress,
                        type: InventoryMovement::TYPE_CREATED,
                        reason: 'Received from purchase order '.$purchaseOrder->purchase_order_number,
                        referenceType: 'purchase_order',
                        referenceId: (int) $purchaseOrder->id,
                        notes: $data['receive_notes'] ?? $purchaseOrder->notes,
                        createdBy: $actorId,
                        fromBranchId: null,
                        toBranchId: $purchaseOrder->branch_id,
                    );
                }
            }

            $purchaseOrder->is_received = true;
            $purchaseOrder->received_at = $data['received_at'] ?? now();
            $purchaseOrder->receive_notes = $data['receive_notes'] ?? $purchaseOrder->receive_notes;
            $purchaseOrder->received_by = $purchaseOrder->received_by ?? $actorId;
            if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT) {
                $purchaseOrder->status = PurchaseOrder::STATUS_CONFIRMED;
            }
            $purchaseOrder->save();
        });

        return $this->findOrFail($purchaseOrder->id);
    }

    public function syncFinancials(PurchaseOrder $purchaseOrder, ?string $preferredStatus = null): PurchaseOrder
    {
        $paidAmount = $this->money((float) SupplierPayment::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->sum('amount'));
        $total = $this->money((float) $purchaseOrder->total);
        $remainingAmount = $this->money(max(0, $total - $paidAmount));

        $status = $this->resolveStatus(
            currentStatus: $preferredStatus ?? (string) $purchaseOrder->status,
            paidAmount: $paidAmount,
            remainingAmount: $remainingAmount,
        );

        $purchaseOrder->fill([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
        ]);
        $purchaseOrder->save();

        return $purchaseOrder->refresh();
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (PurchaseOrder $purchaseOrder): array {
            return [
                $purchaseOrder->id,
                $purchaseOrder->purchase_order_number,
                $purchaseOrder->supplier_id,
                $purchaseOrder->branch_id,
                $purchaseOrder->status,
                $purchaseOrder->is_returned ? 'yes' : 'no',
                $purchaseOrder->total,
                $purchaseOrder->paid_amount,
                $purchaseOrder->remaining_amount,
                $purchaseOrder->order_date?->toDateString(),
            ];
        }, $rows);
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = $this->money((float) ($item['unit_price'] ?? 0));
            $total = $this->money($quantity * $unitPrice);

            return [
                'item_name' => trim((string) $item['item_name']),
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array{subtotal:float,discount:float,tax:float,total:float}
     */
    private function calculateSummary(array $items, float $discount, float $tax): array
    {
        $subtotal = $this->money((float) collect($items)->sum(fn (array $item): float => (float) $item['total']));
        $normalizedDiscount = $this->money(max(0, $discount));
        $normalizedTax = $this->money(max(0, $tax));
        $total = $this->money(max(0, $subtotal - $normalizedDiscount + $normalizedTax));

        return [
            'subtotal' => $subtotal,
            'discount' => $normalizedDiscount,
            'tax' => $normalizedTax,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $items
     */
    private function replaceItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        foreach ($items as $item) {
            $purchaseOrder->items()->create($item);
        }
    }

    private function resolveStatus(string $currentStatus, float $paidAmount, float $remainingAmount): string
    {
        if ($currentStatus === PurchaseOrder::STATUS_CANCELLED) {
            return PurchaseOrder::STATUS_CANCELLED;
        }

        if ($remainingAmount <= 0 && $paidAmount > 0) {
            return PurchaseOrder::STATUS_PAID;
        }

        if ($paidAmount > 0 && $remainingAmount > 0) {
            return PurchaseOrder::STATUS_PARTIALLY_PAID;
        }

        if ($currentStatus === PurchaseOrder::STATUS_CONFIRMED) {
            return PurchaseOrder::STATUS_CONFIRMED;
        }

        return PurchaseOrder::STATUS_DRAFT;
    }

    private function generatePurchaseOrderNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd');
        $sequence = PurchaseOrder::withTrashed()
            ->where('purchase_order_number', 'like', $prefix.'-%')
            ->count() + 1;

        for ($i = 0; $i < 1000; $i++) {
            $purchaseOrderNumber = sprintf('%s-%04d', $prefix, $sequence + $i);

            if (! PurchaseOrder::withTrashed()->where('purchase_order_number', $purchaseOrderNumber)->exists()) {
                return $purchaseOrderNumber;
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

    private function generateReceivedDressCode(PurchaseOrder $purchaseOrder, int $itemId, int $unitSequence): string
    {
        $baseCode = sprintf('PO%d-%d-%03d', $purchaseOrder->id, $itemId, $unitSequence);
        $code = $baseCode;

        for ($attempt = 1; $attempt <= 100; $attempt++) {
            if (! Dress::withTrashed()->where('code', $code)->exists()) {
                return $code;
            }

            $code = sprintf('%s-%02d', $baseCode, $attempt);
        }

        return sprintf('PO%d-%s', $purchaseOrder->id, strtoupper(substr(md5((string) microtime(true)), 0, 8)));
    }

    private function buildReceivedDressName(string $dressCode, PurchaseOrder $purchaseOrder): string
    {
        $parts = array_values(array_filter([
            $dressCode,
            $purchaseOrder->category?->name,
            $purchaseOrder->subcategory?->name,
        ], static fn (?string $value): bool => is_string($value) && trim($value) !== ''));

        return implode('-', $parts);
    }
}
