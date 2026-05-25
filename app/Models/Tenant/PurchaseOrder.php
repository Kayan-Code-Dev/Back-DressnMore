<?php

namespace App\Models\Tenant;

class PurchaseOrder extends BaseTenantModel
{
    protected $fillable = [
        'supplier_id',
        'po_number',
        'status',
        'total',
        'ordered_at',
        'expected_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'expected_at' => 'datetime',
        ];
    }
}
