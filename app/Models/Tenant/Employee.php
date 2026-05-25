<?php

namespace App\Models\Tenant;

class Employee extends BaseTenantModel
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'name',
        'email',
        'phone',
        'position',
        'salary',
        'is_active',
    ];
}
