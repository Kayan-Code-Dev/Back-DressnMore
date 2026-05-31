<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'employee_code',
        'name',
        'email',
        'phone',
        'job_title',
        'branch_id',
        'employment_status',
        'base_salary',
        'hire_date',
        'transport_allowance',
        'housing_allowance',
        'other_allowances',
        'roles',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'other_allowances' => 'decimal:2',
            'hire_date' => 'date',
            'roles' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function custodies(): HasMany
    {
        return $this->hasMany(EmployeeCustody::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }
}
