<?php

namespace App\Models\Tenant;

class Expense extends BaseTenantModel
{
    protected $fillable = [
        'branch_id',
        'category',
        'amount',
        'description',
        'incurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_at' => 'datetime',
        ];
    }
}
