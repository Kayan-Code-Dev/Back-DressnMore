<?php

namespace App\Models\Tenant;

use App\Enums\SecurityDepositTransactionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityDepositTransaction extends BaseTenantModel
{
    public const TYPE_COLLECTED = SecurityDepositTransactionType::COLLECTED->value;
    public const TYPE_DEDUCTED = SecurityDepositTransactionType::DEDUCTED->value;
    public const TYPE_REFUNDED = SecurityDepositTransactionType::REFUNDED->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'invoice_id',
        'type',
        'amount',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
        return SecurityDepositTransactionType::values();
    }
}
