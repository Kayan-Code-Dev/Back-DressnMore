<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends BaseTenantModel
{
    protected $connection = 'tenant';

    protected $fillable = [
        'purchase_order_id',
        'item_name',
        'item_code',
        'description',
        'quantity',
        'unit_price',
        'total',
        'dress_category_id',
        'dress_subcategory_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'dress_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'dress_subcategory_id');
    }
}
