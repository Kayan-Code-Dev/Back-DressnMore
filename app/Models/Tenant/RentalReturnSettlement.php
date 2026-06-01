<?php

namespace App\Models\Tenant;

use App\Enums\RentalReturnSettlementStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalReturnSettlement extends BaseTenantModel
{
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'customer_id',
        'branch_id',
        'expected_return_date',
        'actual_return_date',
        'condition',
        'late_days',
        'late_fee',
        'damage_fee',
        'cleaning_fee',
        'other_fee',
        'total_fees',
        'deposit_amount',
        'deposit_paid_amount',
        'deposit_refund_amount',
        'deposit_withheld_amount',
        'additional_amount_due',
        'settlement_total',
        'status',
        'notes',
        'created_by',
        'settled_by',
        'settled_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_return_date' => 'date',
            'actual_return_date' => 'date',
            'late_days' => 'integer',
            'late_fee' => 'decimal:2',
            'damage_fee' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'other_fee' => 'decimal:2',
            'total_fees' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_paid_amount' => 'decimal:2',
            'deposit_refund_amount' => 'decimal:2',
            'deposit_withheld_amount' => 'decimal:2',
            'additional_amount_due' => 'decimal:2',
            'settlement_total' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isBlocking(): bool
    {
        return in_array($this->status, RentalReturnSettlementStatus::blockingStatuses(), true);
    }
}
