<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends BaseTenantModel
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'status',
    ];

    public function dresses(): HasMany
    {
        return $this->hasMany(Dress::class, 'branch_id');
    }

    public function outboundMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'from_branch_id');
    }

    public function inboundMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'to_branch_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }
}
