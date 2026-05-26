<?php

namespace App\Models\Tenant;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_DRAFT = PurchaseOrderStatus::DRAFT->value;

    public const STATUS_CONFIRMED = PurchaseOrderStatus::CONFIRMED->value;

    public const STATUS_PARTIALLY_PAID = PurchaseOrderStatus::PARTIALLY_PAID->value;

    public const STATUS_PAID = PurchaseOrderStatus::PAID->value;

    public const STATUS_CANCELLED = PurchaseOrderStatus::CANCELLED->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'supplier_id',
        'branch_id',
        'category_id',
        'subcategory_id',
        'purchase_order_number',
        'status',
        'type',
        'is_returned',
        'returned_at',
        'return_notes',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'remaining_amount',
        'order_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'order_date' => 'date',
            'is_returned' => 'boolean',
            'returned_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(DressCategory::class, 'subcategory_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
        return PurchaseOrderStatus::values();
    }
}
