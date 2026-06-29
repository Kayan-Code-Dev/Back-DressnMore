<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'status',
        'plan_id',
        'subscription_starts_at',
        'subscription_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function provisioningLogs(): HasMany
    {
        return $this->hasMany(TenantProvisioningLog::class);
    }
}
