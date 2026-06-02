<?php

namespace App\Services\Tenant;

use App\Enums\HrEmployeeStatus;
use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrDocument;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;

class HrDashboardService
{
    public function __construct(private readonly HrDocumentService $hrDocumentService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?int $branchId = null): array
    {
        $employeeQuery = HrEmployee::query();
        if ($branchId !== null) {
            $employeeQuery->where('branch_id', $branchId);
        }

        $statusCounts = (clone $employeeQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'kpis' => [
                'total_employees' => (clone $employeeQuery)->count(),
                'active_employees' => (int) ($statusCounts[HrEmployeeStatus::ACTIVE->value] ?? 0),
                'inactive_employees' => (int) ($statusCounts[HrEmployeeStatus::INACTIVE->value] ?? 0),
                'suspended_employees' => (int) ($statusCounts[HrEmployeeStatus::SUSPENDED->value] ?? 0),
                'terminated_employees' => (int) ($statusCounts[HrEmployeeStatus::TERMINATED->value] ?? 0),
                'departments_count' => HrDepartment::query()->count(),
                'job_titles_count' => HrJobTitle::query()->count(),
                'documents_count' => HrDocument::query()->count(),
                'expiring_documents_count' => $this->hrDocumentService->countExpiring(),
                'on_leave_today' => 0,
                'late_today' => 0,
                'payroll_this_month' => 0,
                'pending_requests' => 0,
            ],
            'attendance_snapshot' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'day_off' => 0,
            ],
            'payroll_summary' => [
                'gross_salaries' => 0,
                'deductions' => 0,
                'bonuses' => 0,
                'net_payroll' => 0,
            ],
            'upcoming_events' => [],
            'recent_activity' => [],
        ];
    }
}
