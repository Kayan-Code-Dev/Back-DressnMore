<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends BaseTenantModel
{
    public const TYPE_NORMAL = 'normal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_OPENING = 'opening';

    public const TYPE_CLOSING = 'closing';

    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INVOICE = 'invoice';

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_RETURN = 'return';

    public const SOURCE_PURCHASE_ORDER = 'purchase_order';

    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';

    public const SOURCE_CASH_MOVEMENT = 'cash_movement';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_SECURITY_DEPOSIT_COLLECTION = 'security_deposit_collection';

    public const SOURCE_RENTAL_RETURN_SETTLEMENT = 'rental_return_settlement';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'type',
        'source_type',
        'source_id',
        'reference_number',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'difference',
        'is_balanced',
        'branch_id',
        'created_by',
        'approved_by',
        'cancelled_by',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
        'reversed_entry_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'difference' => 'decimal:2',
        'is_balanced' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
