<?php

namespace App\Services\Tenant;

use App\Enums\ReportExportFormat;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use App\Support\Reports\ReportExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionStatementService
{
    public function __construct(private readonly CashMovementService $cashMovementService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function branchSummaries(array $filters): array
    {
        $movementQuery = $this->baseMovementQuery($filters);

        $branches = Branch::query()
            ->with(['cashboxes' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $summaries = [];
        $totalBalance = 0.0;
        $totalEntries = 0;

        foreach ($branches as $branch) {
            $branchFilters = array_merge($filters, ['branch_id' => $branch->id]);
            $branchQuery = $this->baseMovementQuery($branchFilters);
            $entryCount = (clone $branchQuery)->count();
            $balance = round((float) $branch->cashboxes->sum('current_balance'), 2);

            $summaries[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'branch_code' => $branch->branch_code ?? $branch->code,
                'icon' => $this->resolveBranchIcon($branch->name),
                'balance' => $balance,
                'entry_count' => $entryCount,
                'accounts' => $branch->cashboxes->map(fn (Cashbox $cashbox): array => $this->presentAccount($cashbox))->values()->all(),
            ];

            $totalBalance += $balance;
            $totalEntries += $entryCount;
        }

        $unassignedCashboxes = Cashbox::query()
            ->whereNull('branch_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($unassignedCashboxes->isNotEmpty()) {
            $unassignedFilters = array_merge($filters, ['branch_id' => 0]);
            $entryCount = $this->baseMovementQuery($unassignedFilters)->count();
            $balance = round((float) $unassignedCashboxes->sum('current_balance'), 2);

            $summaries[] = [
                'id' => 0,
                'name' => 'حسابات عامة',
                'branch_code' => null,
                'icon' => 'warehouse',
                'balance' => $balance,
                'entry_count' => $entryCount,
                'accounts' => $unassignedCashboxes->map(fn (Cashbox $cashbox): array => $this->presentAccount($cashbox))->values()->all(),
            ];

            $totalBalance += $balance;
            $totalEntries += $entryCount;
        }

        array_unshift($summaries, [
            'id' => 'all',
            'name' => 'الكل',
            'branch_code' => null,
            'icon' => 'all',
            'balance' => round($totalBalance, 2),
            'entry_count' => (clone $movementQuery)->count(),
            'accounts' => Cashbox::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Cashbox $cashbox): array => $this->presentAccount($cashbox))
                ->values()
                ->all(),
        ]);

        return $summaries;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $entries = $this->ledgerEntries($filters);
        $openingBalance = $this->openingBalance($filters);
        $totalRevenues = round(array_sum(array_map(fn (array $row): float => (float) ($row['credit'] ?? 0), $entries)), 2);
        $totalExpenses = round(array_sum(array_map(fn (array $row): float => (float) ($row['debit'] ?? 0), $entries)), 2);
        $currentBalance = round($openingBalance + $totalRevenues - $totalExpenses, 2);

        $lastIncome = collect($entries)->reverse()->first(fn (array $row): bool => ($row['credit'] ?? 0) > 0);
        $lastExpense = collect($entries)->reverse()->first(fn (array $row): bool => ($row['debit'] ?? 0) > 0);

        $cashboxBalance = $this->cashboxBalanceTotal($filters);

        return [
            'opening_balance' => $openingBalance,
            'total_revenues' => $totalRevenues,
            'total_expenses' => $totalExpenses,
            'current_balance' => $currentBalance,
            'available_in_cashbox' => $cashboxBalance,
            'closing_balance' => $currentBalance,
            'last_income_date' => $lastIncome['date'] ?? null,
            'last_expense_date' => $lastExpense['date'] ?? null,
            'entry_count' => count($entries),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function ledger(array $filters): array
    {
        return $this->ledgerEntries($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function closePeriod(array $data, ?int $actorId = null): array
    {
        $closingDate = trim((string) ($data['closing_date'] ?? ''));
        if ($closingDate === '') {
            $closingDate = Carbon::today()->toDateString();
        }

        $branchId = $this->normalizeBranchId($data['branch_id'] ?? null);
        $actualBalance = round((float) ($data['actual_balance'] ?? 0), 2);

        $filters = [
            'branch_id' => $branchId,
            'date_to' => $closingDate,
        ];
        $summary = $this->summary($filters);
        $systemBalance = round((float) $summary['closing_balance'], 2);
        $difference = round($actualBalance - $systemBalance, 2);

        if (abs($difference) >= 0.01) {
            $cashbox = $this->resolveClosingCashbox($branchId);
            if ($cashbox === null) {
                throw new RuntimeException('لا توجد خزنة نشطة لإقفال الفترة');
            }

            $this->cashMovementService->createManual([
                'type' => $difference > 0 ? CashMovement::TYPE_INCOME : CashMovement::TYPE_EXPENSE,
                'direction' => $difference > 0 ? CashMovement::DIRECTION_IN : CashMovement::DIRECTION_OUT,
                'amount' => abs($difference),
                'cashbox_id' => $cashbox->id,
                'reference' => 'CLOSE-'.$closingDate,
                'movement_date' => $closingDate,
                'description' => 'تسوية إقفال الفترة',
                'notes' => 'فرق إقفال: '.number_format($difference, 2, '.', ''),
            ], $actorId);
        }

        return [
            'closing_date' => $closingDate,
            'branch_id' => $branchId,
            'system_balance' => $systemBalance,
            'actual_balance' => $actualBalance,
            'difference' => $difference,
            'adjusted' => abs($difference) >= 0.01,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters, ReportExportFormat $format): StreamedResponse|Response
    {
        $entries = $this->ledgerEntries($filters);
        $summary = $this->summary($filters);
        $branchLabel = $this->resolveBranchLabel($filters['branch_id'] ?? null);

        $headers = ['التاريخ', 'المرجع', 'البيان', 'الفئة', 'الفرع', 'الطرف', 'دائن', 'مدين', 'الرصيد'];
        $rows = array_map(static fn (array $entry): array => [
            $entry['date'],
            $entry['reference'],
            $entry['description'],
            $entry['category'],
            $entry['branch_name'],
            $entry['party'],
            $entry['credit'] ?? '',
            $entry['debit'] ?? '',
            $entry['running_balance'],
        ], $entries);

        return ReportExporter::download(
            $format,
            'transaction-statement',
            'كشف المعاملات — '.$branchLabel,
            $headers,
            $rows,
            [
                'period' => trim(($filters['date_from'] ?? '').' — '.($filters['date_to'] ?? '')),
                'opening_balance' => $summary['opening_balance'],
                'closing_balance' => $summary['closing_balance'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function ledgerEntries(array $filters): array
    {
        $openingBalance = $this->openingBalance($filters);
        $movements = $this->baseMovementQuery($filters)
            ->with(['cashbox.branch'])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $categoryFilter = trim((string) ($filters['category'] ?? ''));
        $balance = $openingBalance;
        $entries = [];

        foreach ($movements as $movement) {
            $entry = $this->presentLedgerEntry($movement);
            if ($categoryFilter !== '' && $entry['category'] !== $categoryFilter) {
                continue;
            }

            $balance += ($entry['credit'] ?? 0) - ($entry['debit'] ?? 0);
            $entry['running_balance'] = round($balance, 2);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseMovementQuery(array $filters): Builder
    {
        $query = CashMovement::query()->where('is_reversed', false);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(reference) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$wildcard]);
            });
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type === 'in') {
            $query->where('direction', CashMovement::DIRECTION_IN);
        } elseif ($type === 'out') {
            $query->where('direction', CashMovement::DIRECTION_OUT);
        }

        $this->applyExactFilter($query, 'type', $filters['movement_type'] ?? null);
        $this->applyBranchFilter($query, $filters['branch_id'] ?? null);

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        return $query;
    }

    private function applyBranchFilter(Builder $query, mixed $branchId): void
    {
        $normalized = $this->normalizeBranchId($branchId);
        if ($normalized === null) {
            return;
        }

        if ($normalized === 0) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('cashbox_id')
                    ->orWhereHas('cashbox', fn (Builder $cashboxQuery) => $cashboxQuery->whereNull('branch_id'));
            });

            return;
        }

        $query->whereHas('cashbox', fn (Builder $builder) => $builder->where('branch_id', $normalized));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function openingBalance(array $filters): float
    {
        $branchId = $this->normalizeBranchId($filters['branch_id'] ?? null);
        $query = Cashbox::query()->where('is_active', true);

        if ($branchId === 0) {
            $query->whereNull('branch_id');
        } elseif ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $openingBalance = round((float) $query->sum('initial_balance'), 2);

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom === '') {
            return $openingBalance;
        }

        $prePeriodMovements = CashMovement::query()
            ->where('is_reversed', false)
            ->whereDate('movement_date', '<', $dateFrom);

        $this->applyBranchFilter($prePeriodMovements, $branchId);

        $netBeforePeriod = (float) $prePeriodMovements->selectRaw(
            'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE -amount END), 0) as net',
            [CashMovement::DIRECTION_IN]
        )->value('net');

        return round($openingBalance + $netBeforePeriod, 2);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cashboxBalanceTotal(array $filters): float
    {
        $query = Cashbox::query()->where('is_active', true);
        $branchId = $this->normalizeBranchId($filters['branch_id'] ?? null);

        if ($branchId === 0) {
            $query->whereNull('branch_id');
        } elseif ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return round((float) $query->sum('current_balance'), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLedgerEntry(CashMovement $movement): array
    {
        $credit = $movement->direction === CashMovement::DIRECTION_IN
            ? round((float) $movement->amount, 2)
            : null;
        $debit = $movement->direction === CashMovement::DIRECTION_OUT
            ? round((float) $movement->amount, 2)
            : null;

        return [
            'id' => $movement->id,
            'date' => $movement->movement_date?->toDateString() ?? $movement->created_at?->toDateString() ?? '',
            'reference' => $movement->reference ?: ('MOV-'.$movement->id),
            'description' => $movement->description ?: '—',
            'category' => $this->resolveCategoryLabel($movement->type),
            'branch_id' => $movement->cashbox?->branch_id,
            'branch_name' => $movement->cashbox?->branch?->name ?? '—',
            'party' => $movement->notes ?: '—',
            'credit' => $credit,
            'debit' => $debit,
            'running_balance' => round((float) ($movement->balance_after ?? 0), 2),
            'status' => $movement->is_reversed ? 'cancelled' : 'completed',
            'direction' => $movement->direction,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAccount(Cashbox $cashbox): array
    {
        return [
            'id' => $cashbox->id,
            'name' => $cashbox->name,
            'account_number' => 'CB-'.str_pad((string) $cashbox->id, 4, '0', STR_PAD_LEFT),
            'branch_id' => $cashbox->branch_id,
            'current_balance' => round((float) $cashbox->current_balance, 2),
            'initial_balance' => round((float) $cashbox->initial_balance, 2),
            'is_active' => (bool) $cashbox->is_active,
        ];
    }

    private function resolveClosingCashbox(?int $branchId): ?Cashbox
    {
        $query = Cashbox::query()->where('is_active', true)->orderBy('id');

        if ($branchId === 0) {
            $query->whereNull('branch_id');
        } elseif ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->first();
    }

    private function resolveBranchLabel(mixed $branchId): string
    {
        $normalized = $this->normalizeBranchId($branchId);
        if ($normalized === null) {
            return 'كل الفروع';
        }

        if ($normalized === 0) {
            return 'حسابات عامة';
        }

        return (string) (Branch::query()->whereKey($normalized)->value('name') ?? 'فرع');
    }

    private function resolveBranchIcon(string $name): string
    {
        $normalized = mb_strtolower($name);

        if (str_contains($normalized, 'ورش')) {
            return 'tools';
        }

        if (str_contains($normalized, 'مصنع')) {
            return 'factory';
        }

        if (str_contains($normalized, 'مخزن') || str_contains($normalized, 'مستودع')) {
            return 'warehouse';
        }

        if (str_contains($normalized, 'رئيس')) {
            return 'building';
        }

        return 'home';
    }

    private function resolveCategoryLabel(string $type): string
    {
        return match ($type) {
            CashMovement::TYPE_INVOICE_PAYMENT => 'مدفوعات عملاء',
            CashMovement::TYPE_EXPENSE => 'مصاريف',
            CashMovement::TYPE_INCOME => 'إيرادات',
            CashMovement::TYPE_SUPPLIER_PAYMENT => 'مدفوعات موردين',
            CashMovement::TYPE_MANUAL_ADJUSTMENT => 'تسوية يدوية',
            CashMovement::TYPE_SECURITY_DEPOSIT_DEDUCTION => 'خصم تأمين',
            CashMovement::TYPE_SECURITY_DEPOSIT_COLLECTION => 'تحصيل تأمين',
            CashMovement::TYPE_SECURITY_DEPOSIT_REFUND => 'رد تأمين',
            default => $type,
        };
    }

    private function normalizeBranchId(mixed $branchId): ?int
    {
        if ($branchId === null || $branchId === '' || $branchId === 'all') {
            return null;
        }

        return (int) $branchId;
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
}
