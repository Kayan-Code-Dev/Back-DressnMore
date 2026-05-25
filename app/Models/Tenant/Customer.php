<?php

namespace App\Models\Tenant;

class Customer extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'notes',
    ];
}
