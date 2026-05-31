<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopCloth extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'workshop_id',
        'cloth_code',
        'customer_name',
        'product_name',
        'workshop_status',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
