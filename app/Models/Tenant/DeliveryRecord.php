<?php

namespace App\Models\Tenant;

class DeliveryRecord extends BaseTenantModel
{
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'delivered_at',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }
}
