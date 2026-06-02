<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrAttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'branch_name' => $this->whenLoaded('employee', fn () => $this->employee?->branch?->name),
            'date' => $this->date?->toDateString(),
            'shift_id' => $this->shift_id,
            'shift_name' => $this->whenLoaded('shift', fn () => $this->shift?->name),
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'late_minutes' => (int) $this->late_minutes,
            'overtime_hours' => (float) $this->overtime_hours,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
