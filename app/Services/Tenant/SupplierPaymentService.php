<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Cashbox;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
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
        private readonly CashMovementService $cashMovementService,
        private readonly JournalEntryPostingService $journalEntryPostingService,
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

        $cashbox = null;
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        $cashboxId = isset($data['cashbox_id']) ? (int) $data['cashbox_id'] : null;

        if ($cashboxId !== null) {
            $cashbox = Cashbox::query()->findOrFail($cashboxId);
            if ((bool) $cashbox->is_active === false) {
                throw ValidationException::withMessages([
                    'cashbox_id' => ['Selected cashbox is inactive'],
                ]);
            }
            if ($branchId !== null && $cashbox->branch_id !== null && (int) $cashbox->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'cashbox_id' => ['Selected cashbox does not belong to the selected branch'],
                ]);
            }
            $branchId = $branchId ?? ($cashbox->branch_id !== null ? (int) $cashbox->branch_id : null);
        }

        if ($purchaseOrder instanceof PurchaseOrder && $purchaseOrder->branch_id !== null) {
            $purchaseOrderBranchId = (int) $purchaseOrder->branch_id;
            if ($branchId !== null && $branchId !== $purchaseOrderBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Payment branch does not match purchase order branch'],
                ]);
            }
            $branchId = $purchaseOrderBranchId;
        }

        if ($cashbox === null && $branchId !== null) {
            $cashbox = Cashbox::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        /** @var SupplierPayment $payment */
        $payment = DB::connection('tenant')->transaction(function () use ($supplier, $purchaseOrder, $branchId, $cashbox, $data, $actorId): SupplierPayment {
            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'branch_id' => $branchId,
                'cashbox_id' => $cashbox?->id,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $expenseCategory = ExpenseCategory::query()->firstOrCreate(
                ['slug' => 'supplier-payments'],
                [
                    'name' => 'Supplier Payments',
                    'description' => 'Auto generated expenses from supplier payments',
                    'status' => ExpenseCategory::STATUS_ACTIVE,
                ],
            );

            $expense = Expense::query()->create([
                'expense_category_id' => $expenseCategory->id,
                'branch_id' => $branchId,
                'cashbox_id' => $cashbox?->id,
                'amount' => round((float) $payment->amount, 2),
                'status' => Expense::STATUS_PAID,
                'method' => $payment->method,
                'vendor' => $supplier->name,
                'reference' => $payment->reference,
                'reference_number' => $purchaseOrder?->purchase_order_number ?: ('SP-'.$payment->id),
                'expense_date' => ($payment->paid_at ?? Carbon::now())->toDateString(),
                'description' => $purchaseOrder instanceof PurchaseOrder
                    ? 'Supplier payment for purchase order '.$purchaseOrder->purchase_order_number
                    : 'Supplier payment',
                'notes' => $payment->notes,
                'created_by' => $actorId,
                'paid_at' => $payment->paid_at ?? Carbon::now(),
            ]);

            $payment->expense_id = $expense->id;
            $payment->save();

            if ($purchaseOrder instanceof PurchaseOrder) {
                $this->purchaseOrderService->syncFinancials($purchaseOrder->refresh(), (string) $purchaseOrder->status);
            }

            $this->supplierService->recalculateCurrentBalance($supplier->refresh());
            $this->cashMovementService->recordSupplierPayment($payment, $actorId);

            return $payment;
        });

        $this->journalEntryPostingService->postFromSupplierPayment($payment, $actorId);

        return $payment->load(['purchaseOrder', 'cashbox', 'expense']);
    }

    public function paginateForSupplier(Supplier $supplier, int $perPage = 15): LengthAwarePaginator
    {
        return $supplier->payments()
            ->with(['purchaseOrder', 'cashbox', 'expense'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForPurchaseOrder(PurchaseOrder $purchaseOrder, int $perPage = 15): LengthAwarePaginator
    {
        return $purchaseOrder->payments()
            ->with(['supplier', 'cashbox', 'expense'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SupplierPayment::query()
            ->with(['supplier', 'purchaseOrder', 'cashbox', 'expense'])
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
