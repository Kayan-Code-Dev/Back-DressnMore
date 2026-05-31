<?php

namespace App\Services\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;

class SupplierAccountService
{
    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Supplier $supplier): array
    {
        $supplier->loadMissing([]);

        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->latest('order_date')
            ->limit(100)
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'purchase_order_number' => $order->purchase_order_number,
                'supplier' => $supplier->name,
                'status' => $order->status,
                'total' => (float) $order->total,
                'paid_amount' => (float) $order->paid_amount,
                'remaining_amount' => (float) $order->remaining_amount,
                'order_date' => $order->order_date?->toDateString() ?? '',
            ])
            ->values()
            ->all();

        $payments = SupplierPayment::query()
            ->with('purchaseOrder')
            ->where('supplier_id', $supplier->id)
            ->latest('paid_at')
            ->limit(100)
            ->get()
            ->map(fn (SupplierPayment $payment): array => [
                'id' => $payment->id,
                'supplier' => $supplier->name,
                'purchase_order_number' => $payment->purchaseOrder?->purchase_order_number ?? '—',
                'amount' => (float) $payment->amount,
                'method' => $payment->method ?? 'cash',
                'reference' => $payment->reference ?? '',
                'paid_at' => $payment->paid_at?->toDateString() ?? '',
                'notes' => $payment->notes ?? '',
            ])
            ->values()
            ->all();

        $returns = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_returned', true)
            ->latest('returned_at')
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'return_number' => 'RET-'.$order->purchase_order_number,
                'date' => $order->returned_at?->toDateString() ?? '',
                'amount' => (float) $order->total,
                'reason' => $order->return_notes ?? '',
            ])
            ->values()
            ->all();

        $statement = $this->buildStatement($orders, $payments, $returns);

        return [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'current_balance' => (float) $supplier->current_balance,
                'status' => $supplier->status,
            ],
            'purchase_orders' => $orders,
            'payments' => $payments,
            'returns' => $returns,
            'statement' => $statement,
        ];
    }

    public function findSupplierOrFail(int $supplierId): Supplier
    {
        return $this->supplierService->findOrFail($supplierId);
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @param  list<array<string, mixed>>  $payments
     * @param  list<array<string, mixed>>  $returns
     * @return list<array<string, mixed>>
     */
    private function buildStatement(array $orders, array $payments, array $returns): array
    {
        $lines = [];

        foreach ($orders as $order) {
            $lines[] = [
                'sort_at' => $order['order_date'],
                'date' => $order['order_date'],
                'description' => 'Purchase order '.$order['purchase_order_number'],
                'debit' => (float) $order['total'],
                'credit' => 0.0,
            ];
        }

        foreach ($payments as $payment) {
            $lines[] = [
                'sort_at' => $payment['paid_at'],
                'date' => $payment['paid_at'],
                'description' => 'Payment '.$payment['reference'],
                'debit' => 0.0,
                'credit' => (float) $payment['amount'],
            ];
        }

        foreach ($returns as $return) {
            $lines[] = [
                'sort_at' => $return['date'],
                'date' => $return['date'],
                'description' => 'Return '.$return['return_number'],
                'debit' => 0.0,
                'credit' => (float) $return['amount'],
            ];
        }

        usort($lines, fn (array $a, array $b): int => strcmp((string) $a['sort_at'], (string) $b['sort_at']));

        $balance = 0.0;
        $statement = [];

        foreach ($lines as $index => $line) {
            $balance += (float) $line['debit'] - (float) $line['credit'];
            $statement[] = [
                'id' => $index + 1,
                'date' => $line['date'],
                'description' => $line['description'],
                'debit' => (float) $line['debit'],
                'credit' => (float) $line['credit'],
                'balance' => round($balance, 2),
            ];
        }

        return $statement;
    }
}
