<?php

namespace App\Models\Tenant;

use App\Enums\ExpenseWorkflowStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_PENDING = ExpenseWorkflowStatus::PENDING->value;

    public const STATUS_APPROVED = ExpenseWorkflowStatus::APPROVED->value;

    public const STATUS_PAID = ExpenseWorkflowStatus::PAID->value;

    public const STATUS_CANCELLED = ExpenseWorkflowStatus::CANCELLED->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'expense_category_id',
        'branch_id',
        'cashbox_id',
        'amount',
        'status',
        'method',
        'vendor',
        'reference',
        'reference_number',
        'expense_date',
        'description',
        'notes',
        'transaction_id',
        'created_by',
        'approved_by',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
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
        return ExpenseWorkflowStatus::values();
    }
}
