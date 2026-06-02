<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrEmployee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class HrEmployeeService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrEmployee::query()
            ->with(['branch', 'department', 'jobTitle'])
            ->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrEmployee
    {
        $employee = HrEmployee::query()->create($data);

        return $employee->load(['branch', 'department', 'jobTitle']);
    }

    public function findOrFail(int $employeeId): HrEmployee
    {
        return HrEmployee::query()
            ->with(['branch', 'department', 'jobTitle'])
            ->findOrFail($employeeId);
    }

    public function update(HrEmployee $employee, array $data): HrEmployee
    {
        $employee->fill($data);
        $employee->save();

        return $employee->refresh()->load(['branch', 'department', 'jobTitle']);
    }

    public function delete(HrEmployee $employee): void
    {
        $employee->delete();
    }

    public function updateStatus(HrEmployee $employee, array $data): HrEmployee
    {
        $employee->status = $data['status'];
        if (array_key_exists('leaving_date', $data)) {
            $employee->leaving_date = $data['leaving_date'];
        }
        if (array_key_exists('notes', $data)) {
            $employee->notes = $data['notes'];
        }
        $employee->save();

        return $employee->refresh()->load(['branch', 'department', 'jobTitle']);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(HrEmployee $employee): array
    {
        $employee->load(['branch', 'department', 'jobTitle']);

        $documentsQuery = $employee->documents();
        $documentsCount = (clone $documentsQuery)->count();
        $expiredDocumentsCount = (clone $documentsQuery)->where('status', 'expired')->count();
        $expiringDocumentsCount = (clone $documentsQuery)->where('status', 'expiring_soon')->count();

        return [
            'employee' => $employee,
            'department' => $employee->department,
            'job_title' => $employee->jobTitle,
            'branch' => $employee->branch,
            'documents_count' => $documentsCount,
            'expired_documents_count' => $expiredDocumentsCount,
            'expiring_documents_count' => $expiringDocumentsCount,
            'attendance' => [
                'present_days_this_month' => 0,
                'late_days_this_month' => 0,
                'absent_days_this_month' => 0,
            ],
            'payroll' => [
                'net_salary_estimate' => null,
                'last_payroll_month' => null,
            ],
            'leaves' => [
                'approved_leaves_this_month' => 0,
                'pending_requests' => 0,
                'leave_balances' => [],
            ],
        ];
    }

    /**
     * @param  Builder<HrEmployee>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereRaw('LOWER(employee_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(full_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(national_id) LIKE ?', [$term]);
            });
        }

        foreach (['branch_id', 'department_id', 'job_title_id', 'status', 'employment_type'] as $field) {
            $value = $filters[$field] ?? null;
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
    }
}
