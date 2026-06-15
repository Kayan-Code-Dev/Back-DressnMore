<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollAdjustment extends BaseTenantModel
{
    public const TYPE_ADVANCE = 'advance';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_COMMISSION = 'commission';

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'effective_month',
        'status',
        'invoice_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_month' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
