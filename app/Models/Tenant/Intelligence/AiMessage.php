<?php

namespace App\Models\Tenant\Intelligence;

use App\Models\Tenant\BaseTenantModel;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends BaseTenantModel
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
        'tokens_used',
        'request_id',
        'generation_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'generation_time_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}