<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Factory extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'factory_code',
        'name',
        'city',
        'address',
        'inventory_name',
        'status',
    ];
}
