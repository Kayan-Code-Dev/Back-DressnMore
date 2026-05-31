<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends BaseTenantModel
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
