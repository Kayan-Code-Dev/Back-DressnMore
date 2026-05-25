<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'status',
        'owner_name',
        'owner_email',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }
}
