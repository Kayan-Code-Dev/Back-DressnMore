<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $connection = 'tenant';
    protected $table = 'gl_accounts';

    protected $fillable = [
        'account_type_id', 'parent_id', 'code', 'name', 'name_en',
        'level', 'is_active', 'current_balance',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(GlAccountType::class, 'account_type_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
