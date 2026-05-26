<?php

namespace App\Models\Tenant;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends BaseTenantModel
{
    public const TYPE_CREATED = InventoryMovementType::CREATED->value;
    public const TYPE_STATUS_CHANGED = InventoryMovementType::STATUS_CHANGED->value;
    public const TYPE_MAINTENANCE = InventoryMovementType::MAINTENANCE->value;
    public const TYPE_SOLD = InventoryMovementType::SOLD->value;
    public const TYPE_RENTED = InventoryMovementType::RENTED->value;
    public const TYPE_RETURNED = InventoryMovementType::RETURNED->value;
    public const TYPE_MANUAL_ADJUSTMENT = InventoryMovementType::MANUAL_ADJUSTMENT->value;
    public const TYPE_BRANCH_TRANSFER = InventoryMovementType::BRANCH_TRANSFER->value;

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
        'from_branch_id',
        'to_branch_id',
    ];

    public function dress(): BelongsTo
    {
        return $this->belongsTo(Dress::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return InventoryMovementType::values();
    }
}
