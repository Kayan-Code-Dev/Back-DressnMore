<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DressCategory extends BaseTenantModel
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    public function dresses(): HasMany
    {
        return $this->hasMany(Dress::class, 'dress_category_id');
    }
}
