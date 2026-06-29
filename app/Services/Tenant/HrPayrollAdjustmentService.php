<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrPayrollAdjustment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class HrPayrollAdjustmentService
{
    public function __construct(private readonly TenantNotifier $notifier) {}
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrPayrollAdjustment::query()
            ->with('employee:id,full_name,employee_code')
            ->latest('id');

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }

        $month = trim((string) ($filters['month'] ?? ''));
        if ($month !== '') {
            $query->whereDate('effective_month', Carbon::parse($month.'-01')->toDateString());
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): HrPayrollAdjustment
    {
        $month = Carbon::parse(($data['month'] ?? date('Y-m')).'-01')->toDateString();

        $adjustment = HrPayrollAdjustment::query()->create([
            'employee_id' => $data['employee_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'effective_month' => $month,
            'status' => $data['status'] ?? 'approved',
            'invoice_id' => $data['invoice_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $adjustment->loadMissing('employee');
        $this->dispatchAdjustmentNotification($adjustment);

        return $adjustment;
    }

    public function delete(HrPayrollAdjustment $adjustment): void
    {
        $adjustment->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(HrPayrollAdjustment $adjustment): array
    {
        $adjustment->loadMissing('employee:id,full_name,employee_code');

        return [
            'id' => $adjustment->id,
            'employee_id' => $adjustment->employee_id,
            'employee_name' => $adjustment->employee?->full_name ?? '',
            'type' => $adjustment->type,
            'amount' => (float) $adjustment->amount,
            'month' => $adjustment->effective_month?->format('Y-m'),
            'status' => $adjustment->status,
            'notes' => $adjustment->notes,
            'invoice_id' => $adjustment->invoice_id,
            'created_at' => $adjustment->created_at?->toISOString(),
            'date' => $adjustment->created_at?->toDateString(),
        ];
    }

    private function dispatchAdjustmentNotification(HrPayrollAdjustment $adjustment): void
    {
        $employee = $adjustment->employee;
        if (! $employee) {
            return;
        }

        $amount = number_format((float) $adjustment->amount, 2);
        $month = $adjustment->effective_month?->format('Y-m') ?? now()->format('Y-m');

        $messages = match ($adjustment->type) {
            HrPayrollAdjustment::TYPE_ADVANCE => [
                'title' => 'سلفة جديدة',
                'message' => sprintf('تم تسجيل سلفة بقيمة %s لشهر %s.', $amount, $month),
                'priority' => 'normal',
                'url' => '/hr/advances-deductions',
            ],
            HrPayrollAdjustment::TYPE_DEDUCTION => [
                'title' => 'خصم / جزاء',
                'message' => sprintf('تم تسجيل خصم بقيمة %s لشهر %s.', $amount, $month),
                'priority' => 'high',
                'url' => '/hr/advances-deductions',
            ],
            HrPayrollAdjustment::TYPE_BONUS => [
                'title' => 'مكافأة',
                'message' => sprintf('تم تسجيل مكافأة بقيمة %s لشهر %s.', $amount, $month),
                'priority' => 'normal',
                'url' => '/hr/bonuses-commissions',
            ],
            HrPayrollAdjustment::TYPE_COMMISSION => [
                'title' => 'عمولة',
                'message' => sprintf('تم تسجيل عمولة بقيمة %s لشهر %s.', $amount, $month),
                'priority' => 'normal',
                'url' => '/hr/bonuses-commissions',
            ],
            default => null,
        };

        if ($messages === null) {
            return;
        }

        if ($employee->user_id) {
            $this->notifier->toUser(
                (int) $employee->user_id,
                $messages['title'],
                $messages['message'],
                'employees',
                $messages['priority'],
                $messages['url'],
            );
        }

        $this->notifier->toUsersWithPermissions(
            ['hr.view'],
            $messages['title'].' — '.$employee->full_name,
            $messages['message'],
            'employees',
            $messages['priority'],
            $messages['url'],
        );
    }
}
