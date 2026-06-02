<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function jobTitles(): HasMany
    {
        return $this->hasMany(HrJobTitle::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }
}
