<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseTenantModel
{
    protected $fillable = [
        'customer_id',
        'branch_id',
        'invoice_number',
        'status',
        'total',
        'paid',
        'remaining',
        'issued_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'remaining' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
