<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use App\Enums\VatType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_ACTIVE = CustomerStatus::ACTIVE->value;

    public const STATUS_INACTIVE = CustomerStatus::INACTIVE->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'code',
        'branch_code',
        'address',
        'phone',
        'vat_enabled',
        'vat_type',
        'vat_value',
        'currency',
        'currency_id',
        'street',
        'building',
        'city_id',
        'notes',
        'inventory_name',
        'image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vat_enabled' => 'boolean',
            'vat_value' => 'decimal:2',
        ];
    }

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashboxes(): HasMany
    {
        return $this->hasMany(Cashbox::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function outgoingProductTransfers(): HasMany
    {
        return $this->hasMany(ProductTransfer::class, 'from_branch_id');
    }

    public function incomingProductTransfers(): HasMany
    {
        return $this->hasMany(ProductTransfer::class, 'to_branch_id');
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }

    /**
     * @return list<string>
     */
    public static function vatTypes(): array
    {
        return VatType::values();
    }
}
