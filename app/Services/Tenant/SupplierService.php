<?php

namespace App\Services\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SupplierService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query()->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(code) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$wildcard]);
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Supplier $supplier): Supplier => $this->withSummary($supplier))
        );

        return $paginator;
    }

    public function create(array $data): Supplier
    {
        $supplier = Supplier::query()->create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'current_balance' => 0,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? Supplier::STATUS_ACTIVE,
        ]);

        return $this->recalculateCurrentBalance($supplier);
    }

    public function findOrFail(int $supplierId): Supplier
    {
        return $this->withSummary(Supplier::query()->findOrFail($supplierId));
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $this->stripSummaryAttributes($supplier);

        $supplier->fill([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? $supplier->status,
        ]);
        $supplier->save();

        return $this->recalculateCurrentBalance($supplier);
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function recalculateCurrentBalance(Supplier $supplier): Supplier
    {
        $this->stripSummaryAttributes($supplier);

        $summary = $this->summary($supplier);
        $currentBalance = $this->money(
            (float) ($supplier->opening_balance ?? 0)
            + $summary['total_remaining']
            - $summary['unlinked_payments']
        );

        $supplier->current_balance = $currentBalance;
        $supplier->save();

        return $this->withSummary($supplier->refresh());
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(function (Supplier $supplier): array {
            $supplier = $this->withSummary($supplier);

            return [
                $supplier->id,
                $supplier->code,
                $supplier->name,
                $supplier->phone,
                $supplier->address,
                $supplier->status,
                $supplier->current_balance,
                $supplier->total_remaining,
            ];
        }, $rows);
    }

    public function withSummary(Supplier $supplier): Supplier
    {
        $summary = $this->summary($supplier);

        $supplier->setAttribute('total_purchase_orders', $this->money($summary['total_purchase_orders']));
        $supplier->setAttribute('total_paid', $this->money($summary['total_paid']));
        $supplier->setAttribute('total_remaining', $this->money($summary['total_remaining']));

        if ($supplier->current_balance === null) {
            $supplier->setAttribute('current_balance', $this->money((float) ($supplier->opening_balance ?? 0)));
        }

        return $supplier;
    }

    /**
     * @return array{total_purchase_orders:float,total_paid:float,total_remaining:float,unlinked_payments:float}
     */
    private function summary(Supplier $supplier): array
    {
        $purchaseOrderQuery = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED);

        $totalPurchaseOrders = $this->money((float) $purchaseOrderQuery->sum('total'));
        $totalRemaining = $this->money((float) (clone $purchaseOrderQuery)->sum('remaining_amount'));
        $totalPaid = $this->money((float) SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->sum('amount'));
        $unlinkedPayments = $this->money((float) SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->whereNull('purchase_order_id')
            ->sum('amount'));

        return [
            'total_purchase_orders' => $totalPurchaseOrders,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'unlinked_payments' => $unlinkedPayments,
        ];
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    private function stripSummaryAttributes(Supplier $supplier): void
    {
        unset(
            $supplier['total_purchase_orders'],
            $supplier['total_paid'],
            $supplier['total_remaining']
        );
    }
}
