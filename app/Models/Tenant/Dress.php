<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dress extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RENTED = 'rented';
    public const STATUS_SOLD = 'sold';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $connection = 'tenant';

    protected $fillable = [
        'dress_category_id',
        'code',
        'name',
        'description',
        'size',
        'color',
        'purchase_price',
        'rental_price',
        'sale_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'rental_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'dress_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(DressImage::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_RENTED,
            self::STATUS_SOLD,
            self::STATUS_MAINTENANCE,
            self::STATUS_UNAVAILABLE,
        ];
    }
}
