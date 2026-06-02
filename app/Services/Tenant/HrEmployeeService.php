<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrEmployee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HrEmployeeService
{
    public function __construct(
        private readonly HrEmployeeAccountService $accountService,
        private readonly HrMetricsService $hrMetricsService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrEmployee::query()
            ->with(['branch', 'department', 'jobTitle', 'user.roles'])
            ->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): HrEmployee
    {
        $account = (array) ($data['user_account'] ?? []);
        unset($data['user_account']);

        return DB::connection('tenant')->transaction(function () use ($data, $account): HrEmployee {
            $employee = HrEmployee::query()->create($data);
            $this->accountService->createForEmployee($employee, $account);

            return $this->loadEmployee($employee->id);
        });
    }

    public function findOrFail(int $employeeId): HrEmployee
    {
        return $this->loadEmployee($employeeId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(HrEmployee $employee, array $data): HrEmployee
    {
        $account = array_key_exists('user_account', $data)
            ? (array) ($data['user_account'] ?? [])
            : null;
        unset($data['user_account']);

        return DB::connection('tenant')->transaction(function () use ($employee, $data, $account): HrEmployee {
            $employee->fill($data);
            $employee->save();

            if ($account !== null) {
                $this->accountService->updateForEmployee($employee, $account);
            } elseif ($employee->user_id) {
                $this->accountService->updateForEmployee($employee, []);
            }

            return $this->loadEmployee($employee->id);
        });
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

        if ($employee->user_id) {
            $this->accountService->updateForEmployee($employee, []);
        }

        return $this->loadEmployee($employee->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(HrEmployee $employee): array
    {
        $employee = $this->loadEmployee($employee->id);

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
            'attendance' => $this->hrMetricsService->employeeMonthAttendance($employee),
            'payroll' => [
                'net_salary_estimate' => (float) $employee->base_salary,
                'last_payroll_month' => null,
            ],
            'leaves' => $this->hrMetricsService->employeeMonthLeaves($employee),
        ];
    }

    private function loadEmployee(int $employeeId): HrEmployee
    {
        return HrEmployee::query()
            ->with(['branch', 'department', 'jobTitle', 'user.roles.permissions'])
            ->findOrFail($employeeId);
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
