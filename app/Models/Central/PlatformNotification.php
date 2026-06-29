<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformNotification extends Model
{
    protected $fillable = [
        'super_admin_id',
        'title',
        'message',
        'category',
        'priority',
        'read_at',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
