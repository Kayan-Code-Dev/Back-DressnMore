<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrPayrollAdjustment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class HrPayrollAdjustmentService
{
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

        return HrPayrollAdjustment::query()->create([
            'employee_id' => $data['employee_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'effective_month' => $month,
            'status' => $data['status'] ?? 'approved',
            'invoice_id' => $data['invoice_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
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
        ];
    }
}
