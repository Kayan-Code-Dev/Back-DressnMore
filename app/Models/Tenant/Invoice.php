<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseTenantModel
{
    use SoftDeletes;

    public const TYPE_RENT = 'rent';
    public const TYPE_SELL = 'sell';
    public const TYPE_TAILORING = 'tailoring';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'type',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'remaining_amount',
        'rent_start_date',
        'rent_end_date',
        'delivery_date',
        'return_date',
        'security_deposit',
        'security_deposit_status',
        'tailoring_due_date',
        'tailoring_notes',
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
            'security_deposit' => 'decimal:2',
            'rent_start_date' => 'date',
            'rent_end_date' => 'date',
            'delivery_date' => 'date',
            'return_date' => 'date',
            'tailoring_due_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_RENT,
            self::TYPE_SELL,
            self::TYPE_TAILORING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CONFIRMED,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_PAID,
            self::STATUS_DELIVERED,
            self::STATUS_RETURNED,
            self::STATUS_CANCELLED,
        ];
    }
}
