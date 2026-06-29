<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Cashbox;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrPayrollPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrPayrollPaymentService
{
    public function __construct(
        private readonly HrPayrollService $payrollService,
        private readonly ExpenseService $expenseService,
        private readonly TenantNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function pay(array $data, ?int $actorId = null): array
    {
        $employee = HrEmployee::query()->with(['branch'])->findOrFail((int) $data['employee_id']);
        $month = (string) $data['month'];
        $period = Carbon::parse($month.'-01');

        $existing = HrPayrollPayment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('payroll_month', $period->toDateString())
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'month' => 'تم صرف راتب هذا الموظف لهذا الشهر مسبقاً.',
            ]);
        }

        $row = $this->payrollService->payrollRowForEmployee($employee, $month);
        $amount = (float) ($row['net_salary'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'لا يوجد مبلغ مستحق للصرف.',
            ]);
        }

        $branchId = (int) ($data['branch_id'] ?? $employee->branch_id ?? 0) ?: null;
        $cashboxId = $this->resolveCashboxId($data, $branchId);

        if (! $cashboxId) {
            throw ValidationException::withMessages([
                'cashbox_id' => 'حدد الخزينة لصرف الراتب.',
            ]);
        }

        $category = ExpenseCategory::query()
            ->where('slug', 'salaries')
            ->where('status', ExpenseCategory::STATUS_ACTIVE)
            ->first();

        return DB::connection('tenant')->transaction(function () use (
            $employee,
            $period,
            $amount,
            $branchId,
            $cashboxId,
            $category,
            $data,
            $actorId,
            $row,
            $month,
        ): array {
            $expense = $this->expenseService->create([
                'expense_category_id' => $category?->id,
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'amount' => $amount,
                'status' => Expense::STATUS_PAID,
                'method' => 'cash',
                'reference' => 'hr_payroll',
                'reference_number' => sprintf('PAY-%s-%s', $employee->employee_code, $period->format('Y-m')),
                'expense_date' => now()->toDateString(),
                'description' => sprintf('صرف راتب %s — %s', $employee->full_name, $period->format('Y-m')),
                'notes' => $data['notes'] ?? null,
            ], $actorId);

            $payment = HrPayrollPayment::query()->create([
                'employee_id' => $employee->id,
                'payroll_month' => $period->toDateString(),
                'amount' => $amount,
                'status' => HrPayrollPayment::STATUS_PAID,
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'expense_id' => $expense->id,
                'paid_at' => now(),
                'paid_by' => $actorId,
                'notes' => $data['notes'] ?? null,
            ]);

            $result = array_merge($row, [
                'status' => 'paid',
                'payment_id' => $payment->id,
                'paid_at' => $payment->paid_at?->toISOString(),
                'expense_id' => $expense->id,
            ]);

            if ($employee->user_id) {
                $this->notifier->toUser(
                    (int) $employee->user_id,
                    'تم صرف راتبك',
                    sprintf('تم صرف راتب شهر %s بمبلغ %s.', $month, number_format($amount, 2)),
                    'employees',
                    'normal',
                    '/hr/payroll',
                );
            }

            $this->notifier->toUsersWithPermissions(
                ['hr.view', 'hr.payroll.view'],
                'صرف راتب موظف',
                sprintf('تم صرف راتب %s لشهر %s.', $employee->full_name, $month),
                'employees',
                'normal',
                '/hr/payroll',
                $actorId,
            );

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCashboxId(array $data, ?int $branchId): ?int
    {
        $cashboxId = (int) ($data['cashbox_id'] ?? 0);
        if ($cashboxId > 0) {
            return $cashboxId;
        }

        if (! $branchId) {
            return null;
        }

        $cashbox = Cashbox::query()
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->first();

        return $cashbox?->id;
    }
}
