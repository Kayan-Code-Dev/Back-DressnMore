<?php

namespace App\Services\Tenant;

use App\Enums\HrCommissionActivity;
use App\Enums\HrCommissionType;
use App\Enums\HrEmployeeStatus;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrPayrollAdjustment;
use App\Models\Tenant\HrPayrollPayment;
use App\Models\Tenant\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class HrPayrollService
{
    public function __construct(
        private readonly HrSettingService $settingService,
        private readonly HrMetricsService $metricsService,
    ) {}

    /**
     * @return array{summary: array<string, float|int>, rows: list<array<string, mixed>>}
     */
    public function payrollForMonth(string $month, ?int $branchId = null): array
    {
        $period = Carbon::parse($month.'-01');
        $rules = $this->settingService->all()['payroll_rules'] ?? [];

        $employees = HrEmployee::query()
            ->with(['branch', 'department'])
            ->where('status', HrEmployeeStatus::ACTIVE->value)
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get();

        $rows = [];
        foreach ($employees as $employee) {
            $rows[] = $this->buildPayrollRow($employee, $period, $rules);
        }

        return [
            'summary' => [
                'gross' => round(collect($rows)->sum('base_salary'), 2),
                'deductions' => round(collect($rows)->sum(fn (array $r) => $r['deductions'] + $r['advances']), 2),
                'bonuses' => round(collect($rows)->sum(fn (array $r) => $r['bonuses'] + $r['commissions']), 2),
                'net' => round(collect($rows)->sum('net_salary'), 2),
                'employee_count' => count($rows),
                'paid_count' => collect($rows)->where('status', 'paid')->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payrollRowForEmployee(HrEmployee $employee, string $month): array
    {
        $period = Carbon::parse($month.'-01');
        $rules = $this->settingService->all()['payroll_rules'] ?? [];

        return $this->buildPayrollRow($employee->loadMissing(['branch', 'department']), $period, $rules);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payrollHistoryForEmployee(HrEmployee $employee, int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $period = now()->subMonths($i);
            $rows[] = $this->buildPayrollRow($employee, $period, $this->settingService->all()['payroll_rules'] ?? []);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function payslipForEmployee(HrEmployee $employee, string $month): array
    {
        $period = Carbon::parse($month.'-01');
        $rules = $this->settingService->all()['payroll_rules'] ?? [];
        $row = $this->buildPayrollRow($employee, $period, $rules);

        return array_merge($row, [
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'job_title' => $employee->jobTitle?->name,
                'branch_name' => $employee->branch?->name,
            ],
            'attendance' => $this->metricsService->employeeMonthAttendance($employee, $period),
            'leaves' => $this->metricsService->employeeMonthLeaves($employee, $period),
            'adjustment_lines' => $this->adjustmentLines($employee->id, $period),
            'commission_breakdown' => $this->commissionBreakdown($employee, $period),
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function buildPayrollRow(HrEmployee $employee, Carbon $period, array $rules): array
    {
        $monthStart = $period->copy()->startOfMonth()->toDateString();
        $monthEnd = $period->copy()->endOfMonth()->toDateString();
        $monthKey = $period->format('Y-m');

        $attendance = $employee->attendanceRecords()
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get();

        $presentDays = $attendance->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentDays = $attendance->where('status', 'absent')->count();
        $lateDays = $attendance->where('status', 'late')->count();
        $overtimeHours = (float) $attendance->sum('overtime_hours');

        $baseSalary = (float) $employee->base_salary;
        $workingDays = max(1, $period->daysInMonth);
        $dailyRate = $baseSalary / $workingDays;

        $attendanceDeduction = 0.0;
        if (! empty($rules['absence_deducts_daily_rate'])) {
            $attendanceDeduction += $absentDays * $dailyRate;
        }
        if (! empty($rules['late_deduction_enabled']) && (float) ($rules['late_deduction_per_minute'] ?? 0) > 0) {
            $lateMinutes = (int) $attendance->sum('late_minutes');
            $attendanceDeduction += $lateMinutes * (float) $rules['late_deduction_per_minute'];
        }

        $overtimePay = 0.0;
        if (! empty($rules['overtime_enabled']) && $overtimeHours > 0) {
            $hourly = $dailyRate / max(1, (float) $employee->working_hours_per_day);
            $overtimePay = $overtimeHours * $hourly * (float) ($rules['overtime_rate_multiplier'] ?? 1.5);
        }

        $adjustments = HrPayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_month', $monthKey.'-01')
            ->where('status', 'approved')
            ->get();

        $advances = (float) $adjustments->where('type', HrPayrollAdjustment::TYPE_ADVANCE)->sum('amount');
        $manualDeductions = (float) $adjustments->where('type', HrPayrollAdjustment::TYPE_DEDUCTION)->sum('amount');
        $bonuses = (float) $adjustments->where('type', HrPayrollAdjustment::TYPE_BONUS)->sum('amount');
        $manualCommissions = (float) $adjustments->where('type', HrPayrollAdjustment::TYPE_COMMISSION)->sum('amount');

        $commissionBreakdown = $this->commissionBreakdown($employee, $period);
        $activityCommissions = (float) ($commissionBreakdown['total'] ?? 0);

        $commissions = $manualCommissions + $activityCommissions;
        $deductions = $attendanceDeduction + $manualDeductions;
        $net = max(0, $baseSalary + $overtimePay + $bonuses + $commissions - $deductions - $advances);

        $payment = HrPayrollPayment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('payroll_month', $monthKey.'-01')
            ->first();

        return [
            'id' => $employee->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'branch_name' => $employee->branch?->name ?? '',
            'base_salary' => round($baseSalary, 2),
            'attendance_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'overtime' => round($overtimePay, 2),
            'advances' => round($advances, 2),
            'deductions' => round($deductions, 2),
            'bonuses' => round($bonuses, 2),
            'commissions' => round($commissions, 2),
            'activity_commissions' => round($activityCommissions, 2),
            'manual_commissions' => round($manualCommissions, 2),
            'net_salary' => round($net, 2),
            'status' => $payment ? 'paid' : 'draft',
            'payment_id' => $payment?->id,
            'paid_at' => $payment?->paid_at?->toISOString(),
            'month' => $monthKey,
        ];
    }

    /**
     * @return array{fixed: float, percentage: float, activity_total: float, rate: float, total: float}
     */
    private function commissionBreakdown(HrEmployee $employee, Carbon $period): array
    {
        $type = (string) ($employee->commission_type ?? HrCommissionType::NONE->value);
        $fixed = 0.0;
        $percentage = 0.0;
        $activityTotal = 0.0;
        $rate = (float) ($employee->commission_rate ?? 0);

        if (in_array($type, [HrCommissionType::FIXED->value, HrCommissionType::MIXED->value], true)) {
            $fixed = (float) ($employee->commission_fixed_amount ?? 0);
        }

        if (
            in_array($type, [HrCommissionType::PERCENTAGE->value, HrCommissionType::MIXED->value], true)
            && $employee->user_id
            && $rate > 0
        ) {
            $activityTotal = $this->activityTotalForEmployee($employee, $period);
            $percentage = round($activityTotal * ($rate / 100), 2);
        }

        return [
            'fixed' => round($fixed, 2),
            'percentage' => $percentage,
            'activity_total' => round($activityTotal, 2),
            'rate' => $rate,
            'total' => round($fixed + $percentage, 2),
        ];
    }

    private function activityTotalForEmployee(HrEmployee $employee, Carbon $period): float
    {
        if (! $employee->user_id) {
            return 0.0;
        }

        $activity = (string) ($employee->commission_activity ?? HrCommissionActivity::ALL->value);
        $types = match ($activity) {
            HrCommissionActivity::SALE->value => [Invoice::TYPE_SELL],
            HrCommissionActivity::RENT->value => [Invoice::TYPE_RENT],
            HrCommissionActivity::TAILORING->value => [Invoice::TYPE_TAILORING],
            default => [Invoice::TYPE_SELL, Invoice::TYPE_RENT, Invoice::TYPE_TAILORING],
        };

        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        return (float) Invoice::query()
            ->whereIn('type', $types)
            ->where('created_by', $employee->user_id)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_DRAFT])
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adjustmentLines(int $employeeId, Carbon $period): array
    {
        return HrPayrollAdjustment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('effective_month', $period->format('Y-m').'-01')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HrPayrollAdjustment $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'amount' => (float) $row->amount,
                'status' => $row->status,
                'notes' => $row->notes,
                'invoice_id' => $row->invoice_id,
            ])
            ->all();
    }
}
