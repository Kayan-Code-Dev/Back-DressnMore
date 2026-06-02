<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'full_name' => $this->full_name,
            'avatar_path' => $this->avatar_path,
            'phone' => $this->phone,
            'email' => $this->email,
            'national_id' => $this->national_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => new HrDepartmentResource($this->department)),
            'department_name' => $this->whenLoaded('department', fn () => $this->department?->name),
            'job_title_id' => $this->job_title_id,
            'job_title' => $this->whenLoaded('jobTitle', fn () => new HrJobTitleResource($this->jobTitle)),
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'joining_date' => $this->joining_date?->toDateString(),
            'leaving_date' => $this->leaving_date?->toDateString(),
            'base_salary' => $this->base_salary,
            'salary_type' => $this->salary_type,
            'working_hours_per_day' => $this->working_hours_per_day,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
