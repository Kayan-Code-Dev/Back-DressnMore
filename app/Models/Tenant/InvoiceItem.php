<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends BaseTenantModel
{
    protected $connection = 'tenant';

    protected $fillable = [
        'invoice_id',
        'dress_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function dress(): BelongsTo
    {
        return $this->belongsTo(Dress::class)->withTrashed();
    }
}
