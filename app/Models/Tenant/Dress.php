<?php

namespace App\Models\Tenant;

use App\Enums\DressStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dress extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = DressStatus::AVAILABLE->value;
    public const STATUS_RENTED = DressStatus::RENTED->value;
    public const STATUS_SOLD = DressStatus::SOLD->value;
    public const STATUS_MAINTENANCE = DressStatus::MAINTENANCE->value;
    public const STATUS_UNAVAILABLE = DressStatus::UNAVAILABLE->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'dress_category_id',
        'dress_subcategory_id',
        'branch_id',
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

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'dress_subcategory_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
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
        return DressStatus::values();
    }

    public function displayName(): string
    {
        $categoryName = $this->category?->name;
        $subcategoryName = $this->subcategory?->name;

        $parts = array_values(array_filter([
            $this->code,
            $categoryName,
            $subcategoryName,
        ], fn (?string $value): bool => is_string($value) && trim($value) !== ''));

        return implode(' - ', $parts);
    }
}
