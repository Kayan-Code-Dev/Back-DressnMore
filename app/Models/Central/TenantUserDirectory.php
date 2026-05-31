<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserDirectory extends Model
{
    protected $connection = 'central';

    protected $table = 'tenant_user_directory';

    protected $fillable = [
        'tenant_id',
        'email',
        'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
