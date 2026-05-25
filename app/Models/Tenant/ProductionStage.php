<?php

namespace App\Models\Tenant;

class ProductionStage extends BaseTenantModel
{
    protected $fillable = [
        'name',
        'sequence',
        'is_active',
    ];
}
