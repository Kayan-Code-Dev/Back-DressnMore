<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlAccountType;
use App\Models\Tenant\GlJournalEntry;
use App\Models\Tenant\GlJournalEntryLine;
use App\Models\Tenant\TreasuryAccount;
use App\Models\Tenant\TreasuryEntry;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function summary(Request $request)
    {
        $accountCount = GlAccount::count();
        $journalCount = GlJournalEntry::count();
        $treasuryCount = TreasuryAccount::count();
        $assets = GlAccount::whereHas('type', fn($q) => $q->where('code', 'ASSET'))->sum('current_balance');
        $liabilities = GlAccount::whereHas('type', fn($q) => $q->where('code', 'LIABILITY'))->sum('current_balance');
        $equity = GlAccount::whereHas('type', fn($q) => $q->where('code', 'EQUITY'))->sum('current_balance');
        $revenue = GlAccount::whereHas('type', fn($q) => $q->where('code', 'REVENUE'))->sum('current_balance');
        $expenses = GlAccount::whereHas('type', fn($q) => $q->where('code', 'EXPENSE'))->sum('current_balance');
        return ApiResponse::success([
            'accounts' => $accountCount,
            'journal_entries' => $journalCount,
            'treasury_accounts' => $treasuryCount,
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
        ]);
    }

    public function ledger(Request $request)
    {
        return $this->getLedger($request);
    }

    public function listAccounts(Request $request)
    {
        $accounts = GlAccount::with(['type', 'children'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();
        return ApiResponse::success($accounts);
    }

    public function getAccountTypes()
    {
        $types = GlAccountType::orderBy('display_order')->get();
        return ApiResponse::success($types);
    }

    public function getLedger(Request $request)
    {
        $accountId = $request->account_id;
        $query = GlJournalEntryLine::with(['journalEntry', 'account'])
            ->orderBy('created_at', 'desc');
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        return ApiResponse::success($query->paginate($request->per_page ?? 20));
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->date ?? now()->format('Y-m-d');
        $assets = GlAccount::with('type')->whereHas('type', fn($q) => $q->where('code', 'ASSET'))->get();
        $liabilities = GlAccount::with('type')->whereHas('type', fn($q) => $q->where('code', 'LIABILITY'))->get();
        $equity = GlAccount::with('type')->whereHas('type', fn($q) => $q->where('code', 'EQUITY'))->get();
        return ApiResponse::success([
            'as_of' => $asOf,
            'assets' => ['items' => $assets, 'total' => $assets->sum('current_balance')],
            'liabilities' => ['items' => $liabilities, 'total' => $liabilities->sum('current_balance')],
            'equity' => ['items' => $equity, 'total' => $equity->sum('current_balance')],
            'liabilities_and_equity' => $liabilities->sum('current_balance') + $equity->sum('current_balance'),
        ]);
    }

    public function incomeStatement(Request $request)
    {
        $from = $request->from_date ?? now()->startOfYear()->format('Y-m-d');
        $to = $request->to_date ?? now()->format('Y-m-d');
        $revenues = GlAccount::with('type')->whereHas('type', fn($q) => $q->where('code', 'REVENUE'))->get();
        $expenses = GlAccount::with('type')->whereHas('type', fn($q) => $q->where('code', 'EXPENSE'))->get();
        $totalRevenue = $revenues->sum('current_balance');
        $totalExpenses = $expenses->sum('current_balance');
        return ApiResponse::success([
            'period' => ['from' => $from, 'to' => $to],
            'revenues' => ['items' => $revenues, 'total' => $totalRevenue],
            'expenses' => ['items' => $expenses, 'total' => $totalExpenses],
            'net_income' => $totalRevenue - $totalExpenses,
        ]);
    }

    public function listTreasuryAccounts(Request $request)
    {
        $accounts = TreasuryAccount::with('branch')->orderBy('name')->paginate($request->per_page ?? 20);
        return ApiResponse::paginated($accounts, $accounts->items());
    }

    public function createTreasuryAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'account_number' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'opening_balance' => 'nullable|numeric',
            'branch_id' => 'nullable|exists:branches,id',
        ]);
        $account = TreasuryAccount::create(array_merge($validated, [
            'current_balance' => $validated['opening_balance'] ?? 0,
            'status' => 'active',
        ]));
        return ApiResponse::success($account, 'Treasury account created', 201);
    }

    public function listTreasuryEntries(Request $request)
    {
        $entries = TreasuryEntry::with(['treasuryAccount', 'fromTreasury', 'creator'])
            ->orderBy('entry_date', 'desc')
            ->paginate($request->per_page ?? 20);
        return ApiResponse::paginated($entries, $entries->items());
    }

    public function createTreasuryEntry(Request $request)
    {
        $validated = $request->validate([
            'treasury_account_id' => 'required|exists:treasury_accounts,id',
            'type' => 'required|in:in,out,transfer',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string',
            'description' => 'required|string',
            'entry_date' => 'required|date',
            'from_treasury_id' => 'nullable|exists:treasury_accounts,id',
        ]);
        DB::beginTransaction();
        try {
            $treasury = TreasuryAccount::find($validated['treasury_account_id']);
            $amount = $validated['amount'];
            if ($validated['type'] === 'out' && $treasury->current_balance < $amount) {
                return ApiResponse::error('Insufficient balance', 422);
            }
            $balanceAfter = match($validated['type']) {
                'in' => $treasury->current_balance + $amount,
                'out' => $treasury->current_balance - $amount,
                'transfer' => $treasury->current_balance + $amount,
                default => $treasury->current_balance,
            };
            $entry = TreasuryEntry::create([...$validated, 'balance_after' => $balanceAfter, 'created_by' => auth()->id()]);
            $treasury->current_balance = $balanceAfter;
            $treasury->save();
            if ($validated['type'] === 'transfer' && $validated['from_treasury_id']) {
                $fromTreasury = TreasuryAccount::find($validated['from_treasury_id']);
                if ($fromTreasury->current_balance < $amount) {
                    DB::rollBack();
                    return ApiResponse::error('Insufficient balance in source', 422);
                }
                $fromTreasury->current_balance -= $amount;
                $fromTreasury->save();
                TreasuryEntry::create([
                    'treasury_account_id' => $validated['from_treasury_id'],
                    'type' => 'out',
                    'amount' => $amount,
                    'description' => 'تحويل إلى: ' . $treasury->name,
                    'entry_date' => $validated['entry_date'],
                    'balance_after' => $fromTreasury->current_balance,
                    'from_treasury_id' => $treasury->id,
                    'created_by' => auth()->id(),
                ]);
            }
            DB::commit();
            return ApiResponse::success($entry, 'Treasury entry created', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function autoPostJournal(Request $request)
    {
        $validated = $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|integer',
            'description' => 'required|string',
            'debit_account_id' => 'required|exists:gl_accounts,id',
            'credit_account_id' => 'required|exists:gl_accounts,id',
            'amount' => 'required|numeric|min:0.01',
        ]);
        DB::beginTransaction();
        try {
            $entry = GlJournalEntry::create([
                'entry_date' => now()->format('Y-m-d'),
                'description' => $validated['description'],
                'reference_type' => $validated['reference_type'],
                'reference_id' => $validated['reference_id'],
                'total_debit' => $validated['amount'],
                'total_credit' => $validated['amount'],
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_at' => now(),
            ]);
            GlJournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['debit_account_id'], 'debit' => $validated['amount'], 'credit' => 0]);
            GlJournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['credit_account_id'], 'debit' => 0, 'credit' => $validated['amount']]);
            $debitAcc = GlAccount::find($validated['debit_account_id']);
            $debitAcc->current_balance += $validated['amount'];
            $debitAcc->save();
            $creditAcc = GlAccount::find($validated['credit_account_id']);
            $creditAcc->current_balance -= $validated['amount'];
            $creditAcc->save();
            DB::commit();
            return ApiResponse::success($entry, 'Journal entry posted', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
