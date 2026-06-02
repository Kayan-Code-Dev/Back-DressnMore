<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrLeaveRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrLeaveService
{
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

        return HrLeaveRequest::query()->create($data)->load(['employee', 'reviewer']);
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

        return $leaveRequest->refresh()->load(['employee', 'reviewer']);
    }

    private function calculateDays(string $fromDate, string $toDate): float
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        return (float) ($from->diffInDays($to) + 1);
    }
}
