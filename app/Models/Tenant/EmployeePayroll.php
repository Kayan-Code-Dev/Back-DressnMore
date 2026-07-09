<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class EmployeePayroll extends BaseTenantModel
{
    protected $fillable = [
        'employee_id','year','month','period_start','period_end',
        'base_salary','bonuses_total','deductions_total','advances_total',
        'attendance_deductions','gross_salary','net_salary',
        'paid_amount','remaining_amount','status',
        'cashbox_id','paid_by','paid_at',
        'cancelled_by','cancelled_at','cancellation_reason','notes',
    ];
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2', 'bonuses_total' => 'decimal:2',
            'deductions_total' => 'decimal:2', 'advances_total' => 'decimal:2',
            'attendance_deductions' => 'decimal:2', 'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2', 'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'period_start' => 'date', 'period_end' => 'date',
            'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function cashbox(): BelongsTo { return $this->belongsTo(Cashbox::class, 'cashbox_id'); }
    public function deductions(): HasMany { return $this->hasMany(EmployeeDeduction::class, 'payroll_id'); }
    public function bonuses(): HasMany { return $this->hasMany(EmployeeBonus::class, 'payroll_id'); }
}
