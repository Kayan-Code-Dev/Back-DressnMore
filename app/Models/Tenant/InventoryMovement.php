<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends BaseTenantModel
{
    public const TYPE_CREATED = 'created';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_SOLD = 'sold';
    public const TYPE_RENTED = 'rented';
    public const TYPE_RETURNED = 'returned';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $connection = 'tenant';

    protected $fillable = [
        'dress_id',
        'type',
        'quantity',
        'reason',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    public function dress(): BelongsTo
    {
        return $this->belongsTo(Dress::class);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_CREATED,
            self::TYPE_STATUS_CHANGED,
            self::TYPE_MAINTENANCE,
            self::TYPE_SOLD,
            self::TYPE_RENTED,
            self::TYPE_RETURNED,
            self::TYPE_MANUAL_ADJUSTMENT,
        ];
    }
}
