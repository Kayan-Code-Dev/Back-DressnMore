<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrShift extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'working_days',
        'branch_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrAttendanceRecord::class, 'shift_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }
}
