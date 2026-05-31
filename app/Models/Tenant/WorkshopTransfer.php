<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopTransfer extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'workshop_id',
        'transfer_code',
        'from_branch',
        'to_workshop',
        'item_name',
        'quantity',
        'status',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
