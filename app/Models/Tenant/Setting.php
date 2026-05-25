<?php

namespace App\Models\Tenant;

class Setting extends BaseTenantModel
{
    protected $connection = 'tenant';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
