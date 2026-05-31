<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Cashbox;
use App\Models\Tenant\Expense;
use App\Models\Tenant\InvoicePayment;
use App\Support\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;

class AccountingService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $branchId = $filters['branch_id'] ?? null;

        $incomeQuery = InvoicePayment::query()
            ->where('status', InvoicePayment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $period['from'])
            ->whereDate('paid_at', '<=', $period['to']);

        if ($branchId) {
            $incomeQuery->whereHas('invoice', fn (Builder $query) => $query->where('branch_id', $branchId));
        }

        $expenseQuery = Expense::query()
            ->where('status', Expense::STATUS_PAID)
            ->whereDate('expense_date', '>=', $period['from'])
            ->whereDate('expense_date', '<=', $period['to']);

        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }

        $totalIncome = round((float) (clone $incomeQuery)->sum('amount'), 2);
        $totalExpenses = round((float) (clone $expenseQuery)->sum('amount'), 2);

        $cashboxBalances = Cashbox::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['name', 'current_balance'])
            ->map(fn (Cashbox $cashbox): array => [
                'name' => $cashbox->name,
                'balance' => round((float) $cashbox->current_balance, 2),
            ])
            ->values()
            ->all();

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_change' => round($totalIncome - $totalExpenses, 2),
            'cashbox_balances' => $cashboxBalances,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function ledger(array $filters): array
    {
        $period = ReportDateRange::resolve($filters);
        $branchId = $filters['branch_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $entries = [];

        $payments = InvoicePayment::query()
            ->with('invoice:id,invoice_number,branch_id')
            ->where('status', InvoicePayment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $period['from'])
            ->whereDate('paid_at', '<=', $period['to'])
            ->when($branchId, fn (Builder $query) => $query->whereHas(
                'invoice',
                fn (Builder $invoiceQuery) => $invoiceQuery->where('branch_id', $branchId)
            ))
            ->latest('paid_at')
            ->get();

        foreach ($payments as $payment) {
            $entries[] = [
                'id' => $payment->id,
                'date' => $payment->paid_at?->toDateString() ?? '',
                'type' => 'credit',
                'reference' => $payment->invoice?->invoice_number ?? "PAY-{$payment->id}",
                'description' => 'Invoice payment',
                'debit' => 0,
                'credit' => round((float) $payment->amount, 2),
                'balance' => 0,
            ];
        }

        $expenses = Expense::query()
            ->where('status', Expense::STATUS_PAID)
            ->whereDate('expense_date', '>=', $period['from'])
            ->whereDate('expense_date', '<=', $period['to'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->latest('expense_date')
            ->get();

        foreach ($expenses as $expense) {
            $entries[] = [
                'id' => 100000 + $expense->id,
                'date' => $expense->expense_date?->toDateString() ?? '',
                'type' => 'debit',
                'reference' => $expense->reference_number ?? "EXP-{$expense->id}",
                'description' => $expense->description ?? $expense->vendor ?? 'Expense',
                'debit' => round((float) $expense->amount, 2),
                'credit' => 0,
                'balance' => 0,
            ];
        }

        usort($entries, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => str_contains(mb_strtolower($entry['reference']), $needle)
                    || str_contains(mb_strtolower($entry['description']), $needle)
            ));
        }

        $runningBalance = 0.0;
        foreach (array_reverse($entries) as $index => $entry) {
            $runningBalance += $entry['credit'] - $entry['debit'];
            $entries[count($entries) - 1 - $index]['balance'] = round($runningBalance, 2);
        }

        return $entries;
    }
}
