<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DressImage extends BaseTenantModel
{
    protected $connection = 'tenant';

    protected $fillable = [
        'dress_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function dress(): BelongsTo
    {
        return $this->belongsTo(Dress::class);
    }
}
