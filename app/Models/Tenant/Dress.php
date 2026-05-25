<?php

namespace App\Models\Tenant;

class Dress extends BaseTenantModel
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'size',
        'color',
        'status',
        'rental_price',
        'sale_price',
        'notes',
    ];
}
