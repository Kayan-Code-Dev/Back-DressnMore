<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'workshop_code',
        'name',
        'city',
        'address',
        'inventory_name',
        'status',
    ];

    public function transfers(): HasMany
    {
        return $this->hasMany(WorkshopTransfer::class);
    }

    public function cloths(): HasMany
    {
        return $this->hasMany(WorkshopCloth::class);
    }
}
