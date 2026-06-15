<?php

namespace App\Services\Tenant;

use App\Enums\HrEmployeeStatus;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrPayrollAdjustment;
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
            ],
            'rows' => $rows,
        ];
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

        $salesCommissions = $this->salesCommissionForEmployee($employee, $period);

        $commissions = $manualCommissions + $salesCommissions;
        $deductions = $attendanceDeduction + $manualDeductions;
        $net = max(0, $baseSalary + $overtimePay + $bonuses + $commissions - $deductions - $advances);

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
            'net_salary' => round($net, 2),
            'status' => 'draft',
            'month' => $monthKey,
        ];
    }

    private function salesCommissionForEmployee(HrEmployee $employee, Carbon $period): float
    {
        if (! $employee->user_id) {
            return 0.0;
        }

        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        return (float) Invoice::query()
            ->where('type', Invoice::TYPE_SELL)
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
