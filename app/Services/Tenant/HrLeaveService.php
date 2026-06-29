<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrLeaveRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrLeaveService
{
    public function __construct(private readonly TenantNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrLeaveRequest::query()
            ->with(['employee', 'reviewer'])
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrLeaveRequest
    {
        if (! isset($data['days'])) {
            $data['days'] = $this->calculateDays($data['from_date'], $data['to_date']);
        }

        $leave = HrLeaveRequest::query()->create($data)->load(['employee', 'reviewer']);

        $employeeName = $leave->employee?->full_name ?? 'موظف';
        $this->notifier->toUsersWithPermissions(
            ['hr.leaves.status', 'hr.view'],
            'طلب إجازة جديد',
            sprintf('طلب إجازة من %s من %s إلى %s.', $employeeName, $leave->from_date?->toDateString(), $leave->to_date?->toDateString()),
            'employees',
            'normal',
            '/hr/leaves',
        );

        return $leave;
    }

    public function findOrFail(int $id): HrLeaveRequest
    {
        return HrLeaveRequest::query()->with(['employee', 'reviewer'])->findOrFail($id);
    }

    public function updateStatus(HrLeaveRequest $leaveRequest, array $data, int $reviewerId): HrLeaveRequest
    {
        $leaveRequest->status = $data['status'];
        $leaveRequest->review_notes = $data['review_notes'] ?? null;
        $leaveRequest->reviewed_by = $reviewerId;
        $leaveRequest->reviewed_at = now();
        $leaveRequest->save();

        $leaveRequest = $leaveRequest->refresh()->load(['employee', 'reviewer']);
        $employee = $leaveRequest->employee;

        if ($employee?->user_id) {
            $statusLabel = match ($leaveRequest->status) {
                'approved' => 'تمت الموافقة على إجازتك',
                'rejected' => 'تم رفض طلب إجازتك',
                'cancelled' => 'تم إلغاء طلب إجازتك',
                default => 'تحديث على طلب الإجازة',
            };

            $this->notifier->toUser(
                (int) $employee->user_id,
                $statusLabel,
                sprintf('طلب الإجازة من %s إلى %s — الحالة: %s', $leaveRequest->from_date?->toDateString(), $leaveRequest->to_date?->toDateString(), $leaveRequest->status),
                'employees',
                $leaveRequest->status === 'rejected' ? 'high' : 'normal',
                '/hr/leaves',
            );
        }

        return $leaveRequest;
    }

    private function calculateDays(string $fromDate, string $toDate): float
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        return (float) ($from->diffInDays($to) + 1);
    }
}
