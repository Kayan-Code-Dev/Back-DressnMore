<?php

namespace App\Models\Tenant;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends BaseTenantModel
{
    use SoftDeletes;

    public const TYPE_INCOME = CashMovementType::INCOME->value;
    public const TYPE_EXPENSE = CashMovementType::EXPENSE->value;
    public const TYPE_INVOICE_PAYMENT = CashMovementType::INVOICE_PAYMENT->value;
    public const TYPE_SECURITY_DEPOSIT_DEDUCTION = CashMovementType::SECURITY_DEPOSIT_DEDUCTION->value;
    public const TYPE_MANUAL_ADJUSTMENT = CashMovementType::MANUAL_ADJUSTMENT->value;

    public const DIRECTION_IN = CashMovementDirection::IN->value;
    public const DIRECTION_OUT = CashMovementDirection::OUT->value;

    public const REFERENCE_EXPENSE = 'expense';
    public const REFERENCE_INVOICE_PAYMENT = 'invoice_payment';
    public const REFERENCE_SECURITY_DEPOSIT_TRANSACTION = 'security_deposit_transaction';

    protected $connection = 'tenant';

    protected $fillable = [
        'type',
        'amount',
        'method',
        'direction',
        'reference_type',
        'reference_id',
        'reference',
        'movement_date',
        'description',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'movement_date' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return CashMovementType::values();
    }

    /**
     * @return list<string>
     */
    public static function directions(): array
    {
        return CashMovementDirection::values();
    }
}
