<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseTenantModel
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'sku',
        'name',
        'description',
        'quantity',
        'cost_price',
        'sale_price',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(ProductTransfer::class);
    }
}
