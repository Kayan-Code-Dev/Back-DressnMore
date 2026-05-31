<?php

namespace App\Services\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SupplierService $supplierService,
        private readonly CashMovementService $cashMovementService
    ) {}

    public function addPayment(Supplier $supplier, array $data, ?int $actorId = null): SupplierPayment
    {
        $purchaseOrder = null;
        if (isset($data['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()->findOrFail((int) $data['purchase_order_id']);
            if ((int) $purchaseOrder->supplier_id !== (int) $supplier->id) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['Purchase order does not belong to the selected supplier'],
                ]);
            }
        }

        /** @var SupplierPayment $payment */
        $payment = DB::connection('tenant')->transaction(function () use ($supplier, $purchaseOrder, $data, $actorId): SupplierPayment {
            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            if ($purchaseOrder instanceof PurchaseOrder) {
                $this->purchaseOrderService->syncFinancials($purchaseOrder->refresh(), (string) $purchaseOrder->status);
            }

            $this->supplierService->recalculateCurrentBalance($supplier->refresh());
            $this->cashMovementService->recordSupplierPayment($payment, $actorId);

            return $payment;
        });

        return $payment->load('purchaseOrder');
    }

    public function paginateForSupplier(Supplier $supplier, int $perPage = 15): LengthAwarePaginator
    {
        return $supplier->payments()
            ->with('purchaseOrder')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForPurchaseOrder(PurchaseOrder $purchaseOrder, int $perPage = 15): LengthAwarePaginator
    {
        return $purchaseOrder->payments()
            ->with('supplier')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SupplierPayment::query()
            ->with(['supplier', 'purchaseOrder'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(reference) LIKE ?', [$needle])
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->whereRaw('LOWER(purchase_order_number) LIKE ?', [$needle]));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
