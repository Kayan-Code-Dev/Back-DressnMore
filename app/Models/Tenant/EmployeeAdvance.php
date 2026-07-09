<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EmployeeAdvance extends BaseTenantModel
{
    protected $fillable = [
        'employee_id','amount','date','reason','cashbox_id','status',
        'created_by','approved_by','approved_at','paid_by','paid_at',
        'cancelled_by','cancelled_at','cancellation_reason',
    ];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function cashbox(): BelongsTo { return $this->belongsTo(Cashbox::class, 'cashbox_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
