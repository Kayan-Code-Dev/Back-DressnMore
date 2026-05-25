<?php

namespace App\Models\Tenant;

class InventoryMovement extends BaseTenantModel
{
    protected $fillable = [
        'dress_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
    ];
}
