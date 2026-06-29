<?php

namespace App\Models\Tenant;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends BaseTenantModel
{
    public const STATUS_PENDING = PaymentStatus::PENDING->value;

    public const STATUS_PAID = PaymentStatus::PAID->value;

    public const STATUS_CANCELLED = PaymentStatus::CANCELLED->value;

    public const TYPE_INVOICE_PAYMENT = PaymentType::INVOICE_PAYMENT->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'cashbox_id',
        'amount',
        'status',
        'payment_type',
        'method',
        'reference',
        'paid_at',
        'cancelled_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return PaymentStatus::values();
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return PaymentType::values();
    }
}
