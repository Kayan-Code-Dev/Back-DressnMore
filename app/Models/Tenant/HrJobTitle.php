<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrJobTitle extends BaseTenantModel
{
    protected $fillable = [
        'department_id',
        'title',
        'status',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'job_title_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }
}
