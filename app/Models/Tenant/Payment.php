<?php

namespace App\Models\Tenant;

class Payment extends BaseTenantModel
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
