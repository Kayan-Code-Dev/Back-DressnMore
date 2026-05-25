<?php

namespace App\Models\Tenant;

class Branch extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'is_active',
    ];
}
