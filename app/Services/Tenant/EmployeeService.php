<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeCustody;
use App\Models\Tenant\EmployeeSalary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeeService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    public function stats(array $filters = []): array
    {
        $rows = $this->baseQuery($filters)->get();

        return [
            'total' => $rows->count(),
            'active' => $rows->where('employment_status', 'active')->count(),
            'on_leave' => $rows->where('employment_status', 'on_leave')->count(),
            'salary_sum' => round((float) $rows->sum('base_salary'), 2),
        ];
    }

    public function findOrFail(int $employeeId): Employee
    {
        return Employee::query()->with('branch')->findOrFail($employeeId);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCustodies(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = EmployeeCustody::query()->with('employee.branch');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(type) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$needle])
                    ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateSalaries(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = EmployeeSalary::query()->with('employee.branch');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(period) LIKE ?', [$needle])
                    ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, int|float>
     */
    public function salaryStats(array $filters = []): array
    {
        $rows = EmployeeSalary::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $needle = '%'.mb_strtolower(trim($search)).'%';
                $query->whereRaw('LOWER(period) LIKE ?', [$needle]);
            })
            ->get();

        return [
            'total_employees' => $rows->unique('employee_id')->count(),
            'paid_count' => $rows->where('status', 'paid')->count(),
            'unpaid_count' => $rows->where('status', 'unpaid')->count(),
            'total_net' => round((float) $rows->sum('net_salary'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Employee::query()->with('branch');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(employee_code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(job_title) LIKE ?', [$needle]);
            });
        }

        return $query;
    }
}
