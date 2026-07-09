<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\EmployeeAdvance;
use App\Models\Tenant\EmployeeBonus;
use App\Models\Tenant\EmployeeDeduction;
use App\Models\Tenant\EmployeePayroll;
use App\Models\Tenant\EmployeeStatementLine;
use App\Models\Tenant\HrEmployee;
use App\Services\Tenant\TenantContext;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeFinancialController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    // ===================== ADVANCES =====================

    public function listAdvances(int $employeeId): JsonResponse
    {
        $rows = EmployeeAdvance::with(['cashbox', 'creator'])
            ->where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->get();
        return ApiResponse::success($rows);
    }

    public function storeAdvance(Request $request, int $employeeId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
            'cashbox_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $employee = HrEmployee::findOrFail($employeeId);
        $user = auth()->user();

        $advance = EmployeeAdvance::create([
            'employee_id' => $employeeId,
            'amount' => $data['amount'],
            'date' => $data['date'],
            'reason' => $data['reason'] ?? null,
            'cashbox_id' => $data['cashbox_id'] ?? null,
            'status' => 'pending',
            'created_by' => $user?->id,
        ]);

        return ApiResponse::success($advance->load(['cashbox', 'creator']), 'تم إنشاء السلفة');
    }

    public function payAdvance(Request $request, int $advanceId): JsonResponse
    {
        $data = $request->validate(['cashbox_id' => ['required', 'integer', 'min:1']]);
        $user = auth()->user();

        $advance = EmployeeAdvance::findOrFail($advanceId);
        if ($advance->status !== 'pending') {
            return ApiResponse::error('السلفة ليست في حالة الانتظار', 422);
        }

        $cashbox = Cashbox::findOrFail($data['cashbox_id']);
        if ((float) $cashbox->current_balance < (float) $advance->amount) {
            return ApiResponse::error('رصيد الخزنة غير كافٍ', 422);
        }

        DB::transaction(function () use ($advance, $cashbox, $user) {
            $advance->update([
                'status' => 'paid',
                'cashbox_id' => $cashbox->id,
                'paid_by' => $user?->id,
                'paid_at' => now(),
            ]);

            $cashbox->decrement('current_balance', (float) $advance->amount);

            // Statement line
            EmployeeStatementLine::create([
                'employee_id' => $advance->employee_id,
                'date' => $advance->date,
                'type' => 'advance_paid',
                'reference_type' => 'advance',
                'reference_id' => $advance->id,
                'description' => 'سلفة: ' . ($advance->reason ?: 'بدون سبب'),
                'debit' => $advance->amount,
                'credit' => 0,
                'balance' => $this->calculateBalance($advance->employee_id, (float) $advance->amount, 0),
                'created_by' => $user?->id,
            ]);
        });

        return ApiResponse::success($advance->fresh(['cashbox']), 'تم دفع السلفة');
    }

    public function cancelAdvance(Request $request, int $advanceId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $user = auth()->user();

        $advance = EmployeeAdvance::findOrFail($advanceId);
        if ($advance->status === 'cancelled') {
            return ApiResponse::error('السلفة ملغاة بالفعل', 422);
        }

        DB::transaction(function () use ($advance, $data, $user) {
            // If paid, reverse cashbox
            if ($advance->status === 'paid' && $advance->cashbox_id) {
                Cashbox::where('id', $advance->cashbox_id)->increment('current_balance', (float) $advance->amount);

                EmployeeStatementLine::create([
                    'employee_id' => $advance->employee_id,
                    'date' => now()->toDateString(),
                    'type' => 'advance_cancelled',
                    'reference_type' => 'advance',
                    'reference_id' => $advance->id,
                    'description' => 'إلغاء سلفة: ' . $data['reason'],
                    'debit' => 0,
                    'credit' => $advance->amount,
                    'balance' => $this->calculateBalance($advance->employee_id, 0, (float) $advance->amount),
                    'created_by' => $user?->id,
                ]);
            }

            $advance->update([
                'status' => 'cancelled',
                'cancelled_by' => $user?->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
        });

        return ApiResponse::success($advance->fresh(), 'تم إلغاء السلفة');
    }

    // ===================== DEDUCTIONS =====================

    public function listDeductions(int $employeeId): JsonResponse
    {
        $rows = EmployeeDeduction::with('creator')
            ->where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->get();
        return ApiResponse::success($rows);
    }

    public function storeDeduction(Request $request, int $employeeId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:absence,penalty,damage,manual,loan_installment,other'],
            'reason' => ['nullable', 'string'],
        ]);

        $user = auth()->user();
        $deduction = EmployeeDeduction::create([
            'employee_id' => $employeeId,
            'amount' => $data['amount'],
            'date' => $data['date'],
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'status' => 'active',
            'created_by' => $user?->id,
        ]);

        return ApiResponse::success($deduction, 'تم إنشاء الخصم');
    }

    public function cancelDeduction(Request $request, int $deductionId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $user = auth()->user();

        $deduction = EmployeeDeduction::findOrFail($deductionId);
        if ($deduction->status === 'cancelled') {
            return ApiResponse::error('الخصم ملغى بالفعل', 422);
        }

        $deduction->update([
            'status' => 'cancelled',
            'cancelled_by' => $user?->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
        ]);

        return ApiResponse::success($deduction, 'تم إلغاء الخصم');
    }

    // ===================== BONUSES =====================

    public function listBonuses(int $employeeId): JsonResponse
    {
        $rows = EmployeeBonus::with('creator')
            ->where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->get();
        return ApiResponse::success($rows);
    }

    public function storeBonus(Request $request, int $employeeId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:performance,overtime,commission,manual,other'],
            'reason' => ['nullable', 'string'],
        ]);

        $user = auth()->user();
        $bonus = EmployeeBonus::create([
            'employee_id' => $employeeId,
            'amount' => $data['amount'],
            'date' => $data['date'],
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'status' => 'active',
            'created_by' => $user?->id,
        ]);

        return ApiResponse::success($bonus, 'تم إنشاء المكافأة');
    }

    public function cancelBonus(Request $request, int $bonusId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $user = auth()->user();

        $bonus = EmployeeBonus::findOrFail($bonusId);
        if ($bonus->status === 'cancelled') {
            return ApiResponse::error('المكافأة ملغاة بالفعل', 422);
        }

        $bonus->update([
            'status' => 'cancelled',
            'cancelled_by' => $user?->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
        ]);

        return ApiResponse::success($bonus, 'تم إلغاء المكافأة');
    }

    // ===================== PAYROLL =====================

    public function listPayrolls(int $employeeId): JsonResponse
    {
        $rows = EmployeePayroll::with(['cashbox', 'deductions', 'bonuses'])
            ->where('employee_id', $employeeId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
        return ApiResponse::success($rows);
    }

    public function generatePayroll(Request $request, int $employeeId): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $employee = HrEmployee::findOrFail($employeeId);
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        // Check existing
        $existing = EmployeePayroll::where('employee_id', $employeeId)
            ->where('year', $year)->where('month', $month)
            ->where('status', '!=', 'cancelled')
            ->first();
        if ($existing) {
            return ApiResponse::error('كشف الراتب لهذا الشهر موجود بالفعل', 422);
        }

        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        // Get active bonuses for period
        $bonuses = EmployeeBonus::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereNull('payroll_id')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $bonusesTotal = $bonuses->sum('amount');

        // Get active deductions for period
        $deductions = EmployeeDeduction::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereNull('payroll_id')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $deductionsTotal = $deductions->sum('amount');

        // Get paid advances for period
        $advances = EmployeeAdvance::where('employee_id', $employeeId)
            ->where('status', 'paid')
            ->whereNull('cancelled_at')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $advancesTotal = $advances->sum('amount');

        $baseSalary = (float) $employee->base_salary;
        $grossSalary = $baseSalary + (float) $bonusesTotal;
        $totalDeductions = (float) $deductionsTotal + (float) $advancesTotal;
        $netSalary = max(0, $grossSalary - $totalDeductions);

        $payroll = DB::transaction(function () use (
            $employeeId, $year, $month, $periodStart, $periodEnd,
            $baseSalary, $bonusesTotal, $deductionsTotal, $advancesTotal,
            $grossSalary, $netSalary, $bonuses, $deductions
        ) {
            $payroll = EmployeePayroll::create([
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'base_salary' => $baseSalary,
                'bonuses_total' => $bonusesTotal,
                'deductions_total' => $deductionsTotal,
                'advances_total' => $advancesTotal,
                'attendance_deductions' => 0,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
                'paid_amount' => 0,
                'remaining_amount' => $netSalary,
                'status' => 'draft',
            ]);

            // Link bonuses and deductions to payroll
            foreach ($bonuses as $b) { $b->update(['payroll_id' => $payroll->id, 'status' => 'applied']); }
            foreach ($deductions as $d) { $d->update(['payroll_id' => $payroll->id, 'status' => 'applied']); }

            return $payroll;
        });

        return ApiResponse::success($payroll->load(['deductions', 'bonuses']), 'تم إنشاء كشف الراتب');
    }

    public function payPayroll(Request $request, int $payrollId): JsonResponse
    {
        $data = $request->validate([
            'cashbox_id' => ['required', 'integer', 'min:1'],
        ]);
        $user = auth()->user();

        $payroll = EmployeePayroll::with('employee')->findOrFail($payrollId);
        if (!in_array($payroll->status, ['draft', 'approved'], true)) {
            return ApiResponse::error('كشف الراتب غير قابل للدفع', 422);
        }

        $cashbox = Cashbox::findOrFail($data['cashbox_id']);
        $amountToPay = (float) $payroll->remaining_amount;
        if ($amountToPay <= 0) {
            return ApiResponse::error('لا يوجد مبلغ مستحق للدفع', 422);
        }
        if ((float) $cashbox->current_balance < $amountToPay) {
            return ApiResponse::error('رصيد الخزنة غير كافٍ', 422);
        }

        DB::transaction(function () use ($payroll, $cashbox, $amountToPay, $user) {
            $payroll->update([
                'status' => 'paid',
                'paid_amount' => $amountToPay,
                'remaining_amount' => 0,
                'cashbox_id' => $cashbox->id,
                'paid_by' => $user?->id,
                'paid_at' => now(),
            ]);

            $cashbox->decrement('current_balance', $amountToPay);

            // Statement line for salary payment
            EmployeeStatementLine::create([
                'employee_id' => $payroll->employee_id,
                'date' => now()->toDateString(),
                'type' => 'payroll_paid',
                'reference_type' => 'payroll',
                'reference_id' => $payroll->id,
                'description' => "راتب {$payroll->month}/{$payroll->year}",
                'debit' => 0,
                'credit' => $amountToPay,
                'balance' => $this->calculateBalance($payroll->employee_id, 0, $amountToPay),
                'created_by' => $user?->id,
            ]);
        });

        return ApiResponse::success($payroll->fresh(['cashbox']), 'تم دفع الراتب');
    }

    public function cancelPayroll(Request $request, int $payrollId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $user = auth()->user();

        $payroll = EmployeePayroll::findOrFail($payrollId);
        if ($payroll->status === 'cancelled') {
            return ApiResponse::error('كشف الراتب ملغى بالفعل', 422);
        }

        DB::transaction(function () use ($payroll, $data, $user) {
            // Reverse cashbox if paid
            if ($payroll->status === 'paid' && $payroll->cashbox_id && (float) $payroll->paid_amount > 0) {
                Cashbox::where('id', $payroll->cashbox_id)->increment('current_balance', (float) $payroll->paid_amount);
            }

            // Unlink bonuses and deductions
            EmployeeBonus::where('payroll_id', $payroll->id)->update(['payroll_id' => null, 'status' => 'active']);
            EmployeeDeduction::where('payroll_id', $payroll->id)->update(['payroll_id' => null, 'status' => 'active']);

            $payroll->update([
                'status' => 'cancelled',
                'cancelled_by' => $user?->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
        });

        return ApiResponse::success($payroll, 'تم إلغاء كشف الراتب');
    }

    // ===================== STATEMENT =====================

    public function statement(int $employeeId): JsonResponse
    {
        $employee = HrEmployee::findOrFail($employeeId);
        $lines = EmployeeStatementLine::where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totals = [
            'total_debit' => $lines->sum('debit'),
            'total_credit' => $lines->sum('credit'),
            'current_balance' => $lines->first()?->balance ?? 0,
        ];

        return ApiResponse::success([
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'base_salary' => $employee->base_salary,
            ],
            'lines' => $lines,
            'totals' => $totals,
        ]);
    }

    // ===================== HELPERS =====================

    private function calculateBalance(int $employeeId, float $debit, float $credit): float
    {
        $last = EmployeeStatementLine::where('employee_id', $employeeId)
            ->orderBy('id', 'desc')
            ->value('balance') ?? 0;
        return (float) $last + $debit - $credit;
    }
}
