<?php

namespace App\Models\Tenant;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SecurityDepositStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseTenantModel
{
    use SoftDeletes;

    public const TYPE_RENT = InvoiceType::RENT->value;

    public const TYPE_SELL = InvoiceType::SELL->value;

    public const TYPE_TAILORING = InvoiceType::TAILORING->value;

    public const STATUS_DRAFT = InvoiceStatus::DRAFT->value;

    public const STATUS_CONFIRMED = InvoiceStatus::CONFIRMED->value;

    public const STATUS_PARTIALLY_PAID = InvoiceStatus::PARTIALLY_PAID->value;

    public const STATUS_PAID = InvoiceStatus::PAID->value;

    public const STATUS_DELIVERED = InvoiceStatus::DELIVERED->value;

    public const STATUS_RETURNED = InvoiceStatus::RETURNED->value;

    public const STATUS_CANCELLED = InvoiceStatus::CANCELLED->value;

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

    public function deliveryRecords(): HasMany
    {
        return $this->hasMany(DeliveryRecord::class);
    }

    public function securityDepositTransactions(): HasMany
    {
        return $this->hasMany(SecurityDepositTransaction::class);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return InvoiceType::values();
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return InvoiceStatus::values();
    }

    /**
     * @return list<string>
     */
    public static function securityDepositStatuses(): array
    {
        return SecurityDepositStatus::values();
    }
}
