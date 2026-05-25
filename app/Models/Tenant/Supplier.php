<?php

namespace App\Models\Tenant;

class Supplier extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'notes',
    ];
}
