<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EmployeeDeduction extends BaseTenantModel
{
    protected $fillable = [
        'employee_id','amount','date','type','reason','status',
        'payroll_id','created_by','cancelled_by','cancelled_at','cancellation_reason',
    ];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'cancelled_at' => 'datetime'];
    }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function payroll(): BelongsTo { return $this->belongsTo(EmployeePayroll::class, 'payroll_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
