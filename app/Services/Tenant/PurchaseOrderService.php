<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            ->with(['supplier', 'items.dress', 'items.category', 'items.subcategory', 'branch', 'category', 'subcategory'])
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
        $depositAmount = $this->normalizeDepositAmount($data['deposit_amount'] ?? 0, $summary['total']);

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = DB::connection('tenant')->transaction(function () use ($data, $items, $summary, $depositAmount, $actorId): PurchaseOrder {
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
                'deposit_amount' => $depositAmount,
                'order_date' => $data['order_date'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->replaceItems($purchaseOrder, $items);

            return $this->syncFinancials($purchaseOrder, (string) $purchaseOrder->status);
        });

        $this->recordDepositIfNeeded($purchaseOrder, $depositAmount, $actorId);
        $this->supplierService->recalculateCurrentBalance($purchaseOrder->supplier()->firstOrFail());

        return $this->findOrFail($purchaseOrder->id);
    }

    public function findOrFail(int $purchaseOrderId): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'items.dress', 'items.category', 'items.subcategory', 'branch', 'category', 'subcategory'])
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
        $depositAmount = $this->normalizeDepositAmount($data['deposit_amount'] ?? $purchaseOrder->deposit_amount ?? 0, $summary['total']);

        /** @var PurchaseOrder $updated */
        $updated = DB::connection('tenant')->transaction(function () use ($purchaseOrder, $data, $items, $summary, $depositAmount, $actorId): PurchaseOrder {
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
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'deposit_amount' => $depositAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $purchaseOrder->created_by ?? $actorId,
            ]);
            $purchaseOrder->save();

            $this->replaceItems($purchaseOrder, $items);

            return $this->syncFinancials($purchaseOrder, (string) $purchaseOrder->status);
        });

        $this->recordDepositIfNeeded($updated, $depositAmount, $actorId);
        $this->supplierService->recalculateCurrentBalance($updated->supplier()->firstOrFail());

        if ($oldSupplierId !== (int) $updated->supplier_id) {
            $oldSupplier = $this->supplierService->findOrFail($oldSupplierId);
            $this->supplierService->recalculateCurrentBalance($oldSupplier);
        }

        return $this->findOrFail($updated->id);
    }

    public function receive(PurchaseOrder $purchaseOrder, array $data, ?int $actorId = null): PurchaseOrder
    {
        if ($purchaseOrder->inventory_received) {
            return $this->findOrFail($purchaseOrder->id);
        }

        $purchaseOrder->load('items');
        $this->validateReceivable($purchaseOrder);

        /** @var PurchaseOrder $received */
        $received = DB::connection('tenant')->transaction(function () use ($purchaseOrder, $data, $actorId): PurchaseOrder {
            foreach ($purchaseOrder->items as $item) {
                $dress = $this->receiveItemAsDress($purchaseOrder, $item, $actorId);
                $item->dress_id = $dress->id;
                $item->save();
            }

            $purchaseOrder->fill([
                'inventory_received' => true,
                'received_at' => $data['received_at'] ?? Carbon::now(),
                'received_by' => $actorId,
            ]);
            $purchaseOrder->save();

            return $purchaseOrder->refresh();
        });

        return $this->findOrFail($received->id);
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

        DB::connection('tenant')->transaction(function () use ($purchaseOrder, $data): void {
            $purchaseOrder->loadMissing('items.dress');

            foreach ($purchaseOrder->items as $item) {
                $dress = $item->dress;
                if (! $dress instanceof Dress) {
                    continue;
                }

                $fromBranchId = $dress->branch_id;
                $dress->branch_id = null;
                $dress->status = Dress::STATUS_UNAVAILABLE;
                $dress->save();

                $this->inventoryService->recordMovement(
                    dress: $dress,
                    type: InventoryMovement::TYPE_RETURNED,
                    quantity: max(1, (int) round((float) $item->quantity)),
                    reason: 'Purchase order returned to supplier',
                    referenceType: PurchaseOrder::class,
                    referenceId: $purchaseOrder->id,
                    notes: $data['return_notes'] ?? null,
                    fromBranchId: $fromBranchId,
                );
            }

            $purchaseOrder->is_returned = true;
            $purchaseOrder->returned_at = $data['returned_at'] ?? now();
            $purchaseOrder->return_notes = $data['return_notes'] ?? null;
            if ($purchaseOrder->status !== PurchaseOrder::STATUS_PAID) {
                $purchaseOrder->status = PurchaseOrder::STATUS_CANCELLED;
            }
            $purchaseOrder->save();
        });

        $this->supplierService->recalculateCurrentBalance($purchaseOrder->supplier()->firstOrFail());

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
                'code' => isset($item['code']) && trim((string) $item['code']) !== '' ? trim((string) $item['code']) : null,
                'dress_category_id' => $item['dress_category_id'] ?? null,
                'dress_subcategory_id' => $item['dress_subcategory_id'] ?? null,
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
        $dressIdsByCode = $purchaseOrder->items()
            ->whereNotNull('code')
            ->whereNotNull('dress_id')
            ->pluck('dress_id', 'code')
            ->all();

        $purchaseOrder->items()->delete();

        foreach ($items as $item) {
            if ($item['code'] !== null && isset($dressIdsByCode[$item['code']])) {
                $item['dress_id'] = $dressIdsByCode[$item['code']];
            }

            $purchaseOrder->items()->create($item);
        }
    }

    private function normalizeDepositAmount(mixed $value, float $total): float
    {
        $depositAmount = $this->money(max(0, (float) ($value ?? 0)));
        if ($depositAmount > $total) {
            throw ValidationException::withMessages([
                'deposit_amount' => ['The deposit amount may not be greater than the purchase order total.'],
            ]);
        }

        return $depositAmount;
    }

    private function recordDepositIfNeeded(PurchaseOrder $purchaseOrder, float $depositAmount, ?int $actorId): void
    {
        if ($depositAmount <= 0) {
            return;
        }

        $reference = $this->depositReference($purchaseOrder);
        $existingDeposit = $this->money((float) SupplierPayment::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('reference', $reference)
            ->sum('amount'));
        $delta = $this->money($depositAmount - $existingDeposit);

        if ($delta <= 0) {
            return;
        }

        app(SupplierPaymentService::class)->addPayment(
            supplier: $purchaseOrder->supplier()->firstOrFail(),
            data: [
                'purchase_order_id' => $purchaseOrder->id,
                'amount' => $delta,
                'method' => 'cash',
                'reference' => $reference,
                'paid_at' => $purchaseOrder->order_date ?? Carbon::now(),
                'notes' => 'Purchase order deposit',
            ],
            actorId: $actorId,
        );
    }

    private function depositReference(PurchaseOrder $purchaseOrder): string
    {
        return 'DEPOSIT-'.$purchaseOrder->purchase_order_number;
    }

    private function validateReceivable(PurchaseOrder $purchaseOrder): void
    {
        $messages = [];

        if ($purchaseOrder->branch_id === null) {
            $messages['branch_id'] = ['A purchase order branch is required before receiving inventory.'];
        }

        foreach ($purchaseOrder->items as $index => $item) {
            $prefix = "items.{$index}";
            if ($item->code === null || trim((string) $item->code) === '') {
                $messages["{$prefix}.code"] = ['An item code is required before receiving inventory.'];
            }
            if ($item->dress_category_id === null) {
                $messages["{$prefix}.dress_category_id"] = ['A dress category is required before receiving inventory.'];
            }
            if ($item->dress_subcategory_id === null) {
                $messages["{$prefix}.dress_subcategory_id"] = ['A dress subcategory is required before receiving inventory.'];
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function receiveItemAsDress(PurchaseOrder $purchaseOrder, mixed $item, ?int $actorId): Dress
    {
        $code = trim((string) $item->code);
        $dress = Dress::withTrashed()->where('code', $code)->first();
        $wasCreated = ! $dress instanceof Dress;
        $fromBranchId = $dress?->branch_id;

        if ($wasCreated) {
            $dress = new Dress;
            $dress->code = $code;
            $dress->status = Dress::STATUS_AVAILABLE;
        } elseif (method_exists($dress, 'trashed') && $dress->trashed()) {
            $dress->restore();
        }

        $dress->fill([
            'dress_category_id' => $item->dress_category_id,
            'dress_subcategory_id' => $item->dress_subcategory_id,
            'branch_id' => $purchaseOrder->branch_id,
            'name' => $this->buildDressDisplayName($code, (int) $item->dress_category_id, (int) $item->dress_subcategory_id),
            'description' => $item->item_name,
            'purchase_price' => $item->unit_price,
            'status' => Dress::STATUS_AVAILABLE,
        ]);
        $dress->save();

        $this->inventoryService->recordMovement(
            dress: $dress,
            type: $wasCreated ? InventoryMovement::TYPE_CREATED : InventoryMovement::TYPE_MANUAL_ADJUSTMENT,
            quantity: max(1, (int) round((float) $item->quantity)),
            reason: 'Purchase order received',
            referenceType: PurchaseOrder::class,
            referenceId: $purchaseOrder->id,
            notes: $item->item_name,
            createdBy: $actorId,
            fromBranchId: $fromBranchId,
            toBranchId: $purchaseOrder->branch_id,
        );

        return $dress->refresh();
    }

    private function buildDressDisplayName(string $code, int $categoryId, int $subcategoryId): string
    {
        $category = DressCategory::query()->find($categoryId);
        $subcategory = DressCategory::query()->find($subcategoryId);

        $parts = array_values(array_filter([
            $code,
            $category?->name,
            $subcategory?->name,
        ], fn (?string $value): bool => is_string($value) && trim($value) !== ''));

        return implode('-', $parts);
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
}
