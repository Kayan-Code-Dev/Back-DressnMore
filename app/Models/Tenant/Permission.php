<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends BaseTenantModel
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'display_name',
        'key',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
