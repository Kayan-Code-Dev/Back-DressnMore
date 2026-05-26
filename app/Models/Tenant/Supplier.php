<?php

namespace App\Models\Tenant;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_ACTIVE = SupplierStatus::ACTIVE->value;

    public const STATUS_INACTIVE = SupplierStatus::INACTIVE->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'code',
        'name',
        'phone',
        'whatsapp',
        'email',
        'address',
        'tax_number',
        'opening_balance',
        'current_balance',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return SupplierStatus::values();
    }
}
