<?php

namespace App\Models\Tenant;

use App\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends BaseTenantModel
{
    use SoftDeletes;

    public const STATUS_ACTIVE = ExpenseStatus::ACTIVE->value;
    public const STATUS_INACTIVE = ExpenseStatus::INACTIVE->value;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return ExpenseStatus::values();
    }
}
