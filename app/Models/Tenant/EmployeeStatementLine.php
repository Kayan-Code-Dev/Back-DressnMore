<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EmployeeStatementLine extends BaseTenantModel
{
    protected $fillable = [
        'employee_id','date','type','reference_type','reference_id',
        'description','debit','credit','balance','created_by',
    ];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance' => 'decimal:2'];
    }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
}
