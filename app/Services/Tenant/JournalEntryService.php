<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JournalEntryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with([
                'branch:id,name',
                'creator:id,name',
                'lines' => fn ($query) => $query->orderBy('id'),
            ])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): JournalEntry
    {
        return JournalEntry::query()
            ->with([
                'lines.account:id,code,name',
                'lines.branch:id,name',
                'branch:id,name',
                'creator:id,name',
                'approver:id,name',
                'canceller:id,name',
                'reversedEntry:id,entry_number',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float|bool>
     */
    public function summary(array $filters = []): array
    {
        $query = $this->filteredQuery($filters);

        $rows = (clone $query)->get(['total_debit', 'total_credit', 'difference', 'status', 'is_balanced']);

        return [
            'total_debit' => round((float) $rows->sum('total_debit'), 2),
            'total_credit' => round((float) $rows->sum('total_credit'), 2),
            'difference' => round((float) $rows->sum('total_debit') - (float) $rows->sum('total_credit'), 2),
            'approved_count' => $rows->where('status', JournalEntry::STATUS_APPROVED)->count(),
            'draft_count' => $rows->where('status', JournalEntry::STATUS_DRAFT)->count(),
            'cancelled_count' => $rows->where('status', JournalEntry::STATUS_CANCELLED)->count(),
            'unbalanced_count' => $rows->where('is_balanced', false)->count(),
            'entries_count' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $actorId): JournalEntry
    {
        $lines = $data['lines'] ?? [];
        $this->assertLineRules($lines, false);

        return DB::connection('tenant')->transaction(function () use ($data, $lines, $actorId): JournalEntry {
            $totals = $this->computeTotals($lines);

            $entry = JournalEntry::query()->create([
                'entry_number' => $this->generateEntryNumber(Carbon::parse($data['entry_date'])),
                'entry_date' => $data['entry_date'],
                'type' => $data['type'] ?? JournalEntry::TYPE_NORMAL,
                'source_type' => $data['source_type'] ?? JournalEntry::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => JournalEntry::STATUS_DRAFT,
                'total_debit' => $totals['total_debit'],
                'total_credit' => $totals['total_credit'],
                'difference' => $totals['difference'],
                'is_balanced' => $totals['is_balanced'],
                'branch_id' => $data['branch_id'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->syncLines($entry, $lines);

            return $this->findOrFail($entry->id);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JournalEntry $entry, array $data, ?int $actorId): JournalEntry
    {
        $this->assertEditable($entry);

        $lines = $data['lines'] ?? null;
        if ($lines !== null) {
            $this->assertLineRules($lines, false);
        }

        return DB::connection('tenant')->transaction(function () use ($entry, $data, $lines): JournalEntry {
            $payload = array_filter([
                'entry_date' => $data['entry_date'] ?? null,
                'type' => $data['type'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : null,
            ], fn ($value) => $value !== null);

            if ($lines !== null) {
                $totals = $this->computeTotals($lines);
                $payload = array_merge($payload, $totals);
                $this->syncLines($entry, $lines);
            }

            if ($payload !== []) {
                $entry->update($payload);
            }

            return $this->findOrFail($entry->id);
        });
    }

    public function approve(JournalEntry $entry, ?int $actorId): JournalEntry
    {
        if ($entry->isCancelled()) {
            throw ValidationException::withMessages(['status' => ['Cannot approve a cancelled journal entry.']]);
        }

        if (! $entry->isDraft()) {
            throw ValidationException::withMessages(['status' => ['Only draft entries can be approved.']]);
        }

        $entry->loadMissing('lines');
        $this->assertLineRules($entry->lines->map(fn (JournalEntryLine $line) => [
            'account_id' => $line->account_id,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'description' => $line->description,
            'branch_id' => $line->branch_id,
        ])->all(), true);

        if (! $entry->is_balanced) {
            throw ValidationException::withMessages([
                'is_balanced' => ['Debit and credit totals must be equal before approval.'],
            ]);
        }

        $entry->update([
            'status' => JournalEntry::STATUS_APPROVED,
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        return $this->findOrFail($entry->id);
    }

    public function cancel(JournalEntry $entry, ?string $reason, ?int $actorId): JournalEntry
    {
        if ($entry->isCancelled()) {
            throw ValidationException::withMessages(['status' => ['Journal entry is already cancelled.']]);
        }

        if (! in_array($entry->status, [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['status' => ['Journal entry cannot be cancelled.']]);
        }

        $entry->update([
            'status' => JournalEntry::STATUS_CANCELLED,
            'cancelled_by' => $actorId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $this->findOrFail($entry->id);
    }

    public function reverse(JournalEntry $entry, ?int $actorId): JournalEntry
    {
        if (! $entry->isApproved()) {
            throw ValidationException::withMessages(['status' => ['Only approved entries can be reversed.']]);
        }

        $entry->loadMissing('lines');

        return DB::connection('tenant')->transaction(function () use ($entry, $actorId): JournalEntry {
            $reversalLines = $entry->lines->map(fn (JournalEntryLine $line) => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $line->description ? 'Reversal: '.$line->description : 'Reversal line',
                'branch_id' => $line->branch_id,
            ])->all();

            $totals = $this->computeTotals($reversalLines);

            $reversal = JournalEntry::query()->create([
                'entry_number' => $this->generateEntryNumber(now()),
                'entry_date' => now()->toDateString(),
                'type' => JournalEntry::TYPE_REVERSAL,
                'source_type' => JournalEntry::SOURCE_SYSTEM,
                'source_id' => $entry->id,
                'reference_number' => $entry->entry_number,
                'description' => 'Reversal of '.$entry->entry_number,
                'status' => JournalEntry::STATUS_APPROVED,
                'total_debit' => $totals['total_debit'],
                'total_credit' => $totals['total_credit'],
                'difference' => $totals['difference'],
                'is_balanced' => $totals['is_balanced'],
                'branch_id' => $entry->branch_id,
                'created_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'reversed_entry_id' => $entry->id,
            ]);

            $this->syncLines($reversal, $reversalLines);

            return $this->findOrFail($reversal->id);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters): array
    {
        return $this->filteredQuery($filters)
            ->with(['branch:id,name', 'creator:id,name'])
            ->orderByDesc('entry_date')
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'entry_number' => $entry->entry_number,
                'entry_date' => $entry->entry_date?->toDateString(),
                'type' => $entry->type,
                'source_type' => $entry->source_type,
                'reference_number' => $entry->reference_number,
                'description' => $entry->description,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
                'difference' => (float) $entry->difference,
                'status' => $entry->status,
                'branch' => $entry->branch?->name,
                'created_by' => $entry->creator?->name,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function createFromSource(array $header, array $lines, ?int $actorId): JournalEntry
    {
        $sourceType = (string) ($header['source_type'] ?? JournalEntry::SOURCE_SYSTEM);
        $sourceId = isset($header['source_id']) ? (int) $header['source_id'] : null;

        if ($sourceId !== null) {
            $existing = $this->findBySource($sourceType, $sourceId);
            if ($existing instanceof JournalEntry) {
                return $this->findOrFail($existing->id);
            }
        }

        $this->assertLineRules($lines, true);
        $totals = $this->computeTotals($lines);

        if (! $totals['is_balanced']) {
            throw new HttpException(422, 'Generated journal entries must be balanced.');
        }

        return DB::connection('tenant')->transaction(function () use ($header, $lines, $totals, $actorId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_number' => $this->generateEntryNumber(Carbon::parse($header['entry_date'] ?? now())),
                'entry_date' => $header['entry_date'] ?? now()->toDateString(),
                'type' => $header['type'] ?? JournalEntry::TYPE_NORMAL,
                'source_type' => $header['source_type'] ?? JournalEntry::SOURCE_SYSTEM,
                'source_id' => $header['source_id'] ?? null,
                'reference_number' => $header['reference_number'] ?? null,
                'description' => $header['description'] ?? null,
                'status' => JournalEntry::STATUS_APPROVED,
                'total_debit' => $totals['total_debit'],
                'total_credit' => $totals['total_credit'],
                'difference' => $totals['difference'],
                'is_balanced' => true,
                'branch_id' => $header['branch_id'] ?? null,
                'created_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            $this->syncLines($entry, $lines);

            return $this->findOrFail($entry->id);
        });
    }

    public function findBySource(string $sourceType, int $sourceId): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', '!=', JournalEntry::STATUS_CANCELLED)
            ->first();
    }

    public function cancelBySource(string $sourceType, int $sourceId, ?int $actorId): ?JournalEntry
    {
        $entry = $this->findBySource($sourceType, $sourceId);
        if (! $entry instanceof JournalEntry) {
            return null;
        }

        if ($entry->isCancelled()) {
            return $entry;
        }

        return $this->cancel($entry, 'Cancelled because source document was reversed or cancelled.', $actorId);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = JournalEntry::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('lines', function (Builder $lineQuery) use ($search): void {
                        $lineQuery->where('account_name', 'like', "%{$search}%")
                            ->orWhere('account_code', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['source_type']) && $filters['source_type'] !== 'all') {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['account_id'])) {
            $accountId = (int) $filters['account_id'];
            $query->whereHas('lines', fn (Builder $lineQuery) => $lineQuery->where('account_id', $accountId));
        }

        if (isset($filters['is_balanced']) && $filters['is_balanced'] !== '' && $filters['is_balanced'] !== 'all') {
            $query->where('is_balanced', filter_var($filters['is_balanced'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    private function generateEntryNumber(Carbon $date): string
    {
        $prefix = 'JE-'.$date->format('Ymd').'-';
        $latest = JournalEntry::query()
            ->where('entry_number', 'like', $prefix.'%')
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $sequence = 1;
        if (is_string($latest)) {
            $parts = explode('-', $latest);
            $sequence = ((int) end($parts)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{total_debit: float, total_credit: float, difference: float, is_balanced: bool}
     */
    private function computeTotals(array $lines): array
    {
        $totalDebit = round(array_sum(array_map(fn ($line) => (float) ($line['debit'] ?? 0), $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn ($line) => (float) ($line['credit'] ?? 0), $lines)), 2);
        $difference = round($totalDebit - $totalCredit, 2);

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.009,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertLineRules(array $lines, bool $requireBalanced): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => ['At least two lines are required.']]);
        }

        foreach ($lines as $index => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => ['Each line must contain either debit or credit, not both.'],
                ]);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => ['Each line must contain a debit or credit amount.'],
                ]);
            }

            if (empty($line['account_id'])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => ['Account is required.'],
                ]);
            }
        }

        if ($requireBalanced) {
            $totals = $this->computeTotals($lines);
            if (! $totals['is_balanced']) {
                throw ValidationException::withMessages([
                    'is_balanced' => ['Debit and credit totals must be equal.'],
                ]);
            }
        }
    }

    private function assertEditable(JournalEntry $entry): void
    {
        if ($entry->isCancelled()) {
            throw ValidationException::withMessages(['status' => ['Cancelled entries cannot be edited.']]);
        }

        if ($entry->isApproved()) {
            throw ValidationException::withMessages(['status' => ['Approved entries cannot be edited directly.']]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(JournalEntry $entry, array $lines): void
    {
        $entry->lines()->delete();

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = Account::query()->findOrFail((int) $line['account_id']);

            JournalEntryLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
                'branch_id' => $line['branch_id'] ?? $entry->branch_id,
                'cost_center_id' => $line['cost_center_id'] ?? null,
            ]);
        }

        $entry->refresh();
    }
}
