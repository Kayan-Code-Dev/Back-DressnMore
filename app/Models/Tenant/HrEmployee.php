<?php

namespace App\Models\Tenant;

use App\Enums\HrEmployeeStatus;
use App\Enums\HrEmploymentType;
use App\Enums\HrSalaryType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployee extends BaseTenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'employee_code',
        'full_name',
        'avatar_path',
        'phone',
        'email',
        'national_id',
        'date_of_birth',
        'gender',
        'address',
        'branch_id',
        'department_id',
        'job_title_id',
        'employment_type',
        'status',
        'joining_date',
        'leaving_date',
        'base_salary',
        'salary_type',
        'working_hours_per_day',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'base_salary' => 'decimal:2',
            'working_hours_per_day' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(HrJobTitle::class, 'job_title_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrDocument::class, 'employee_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrAttendanceRecord::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(HrLeaveRequest::class, 'employee_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return HrEmployeeStatus::values();
    }

    /**
     * @return list<string>
     */
    public static function employmentTypes(): array
    {
        return HrEmploymentType::values();
    }

    /**
     * @return list<string>
     */
    public static function salaryTypes(): array
    {
        return HrSalaryType::values();
    }
}
