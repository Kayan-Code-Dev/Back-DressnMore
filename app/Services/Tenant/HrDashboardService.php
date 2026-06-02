<?php

namespace App\Services\Tenant;

use App\Enums\HrEmployeeStatus;
use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrDocument;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;

class HrDashboardService
{
    public function __construct(
        private readonly HrDocumentService $hrDocumentService,
        private readonly HrMetricsService $hrMetricsService,
    ) {}

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

        $today = now()->toDateString();
        $attendanceSnapshot = $this->hrMetricsService->attendanceSnapshotForDate($today, $branchId);
        $payrollSummary = $this->hrMetricsService->payrollEstimate($branchId);

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
                'on_leave_today' => $this->hrMetricsService->countOnLeaveToday($branchId),
                'late_today' => $attendanceSnapshot['late'],
                'payroll_this_month' => $payrollSummary['net_payroll'],
                'pending_requests' => $this->hrMetricsService->countPendingLeaveRequests($branchId),
            ],
            'attendance_snapshot' => $attendanceSnapshot,
            'payroll_summary' => $payrollSummary,
            'upcoming_events' => $this->hrMetricsService->upcomingEvents($branchId),
            'recent_activity' => $this->hrMetricsService->recentActivity($branchId),
        ];
    }
}
