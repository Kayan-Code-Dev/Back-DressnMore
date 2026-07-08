<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollPayment extends BaseTenantModel
{
    public const STATUS_PAID = 'paid';

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id',
        'payroll_month',
        'amount',
        'status',
        'branch_id',
        'cashbox_id',
        'expense_id',
        'paid_at',
        'paid_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payroll_month' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }
}
