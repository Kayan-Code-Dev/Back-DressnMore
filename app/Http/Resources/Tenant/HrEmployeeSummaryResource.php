<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrEmployeeSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $summary */
        $summary = $this->resource;
        $employee = $summary['employee'];

        return [
            'employee' => new HrEmployeeResource($employee),
            'department' => $summary['department'] ? new HrDepartmentResource($summary['department']) : null,
            'job_title' => $summary['job_title'] ? new HrJobTitleResource($summary['job_title']) : null,
            'branch' => $summary['branch'] ? [
                'id' => $summary['branch']->id,
                'name' => $summary['branch']->name,
            ] : null,
            'documents_count' => $summary['documents_count'],
            'expired_documents_count' => $summary['expired_documents_count'],
            'expiring_documents_count' => $summary['expiring_documents_count'],
            'attendance' => $summary['attendance'],
            'payroll' => $summary['payroll'],
            'leaves' => $summary['leaves'],
        ];
    }
}
