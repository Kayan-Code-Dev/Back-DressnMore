<?php

namespace App\Models\Tenant;

use App\Enums\DeliveryRecordType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRecord extends BaseTenantModel
{
    public const TYPE_DELIVERED = DeliveryRecordType::DELIVERED->value;
    public const TYPE_RETURNED = DeliveryRecordType::RETURNED->value;
    public const TYPE_CANCELLED_DELIVERY = DeliveryRecordType::CANCELLED_DELIVERY->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'invoice_id',
        'type',
        'delivered_at',
        'returned_at',
        'receiver_name',
        'receiver_phone',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return DeliveryRecordType::values();
    }
}
