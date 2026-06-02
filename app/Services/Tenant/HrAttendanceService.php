<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrAttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrAttendanceService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrAttendanceRecord::query()
            ->with(['shift', 'employee.branch'])
            ->latest('date')
            ->latest('id');

        if (! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->whereHas('employee', fn ($builder) => $builder->where('branch_id', $filters['branch_id']));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrAttendanceRecord
    {
        return HrAttendanceRecord::query()->updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'date' => $data['date'],
            ],
            $data,
        )->load(['shift', 'employee.branch']);
    }

    public function findOrFail(int $id): HrAttendanceRecord
    {
        return HrAttendanceRecord::query()->with(['shift', 'employee.branch'])->findOrFail($id);
    }

    public function update(HrAttendanceRecord $record, array $data): HrAttendanceRecord
    {
        $record->fill($data);
        $record->save();

        return $record->refresh()->load(['shift', 'employee.branch']);
    }
}
