<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseTenantModel
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'phone',
        'whatsapp',
        'email',
        'address',
        'national_id',
        'notes',
        'status',
    ];
}
