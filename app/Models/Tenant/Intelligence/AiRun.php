<?php

namespace App\Models\Tenant\Intelligence;

use App\Models\Tenant\BaseTenantModel;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends BaseTenantModel
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'message_id',
        'assistant_message_id',
        'status',
        'error_message',
        'total_tokens',
        'input_tokens',
        'output_tokens',
        'generation_time_ms',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'total_tokens' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'generation_time_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'assistant_message_id');
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(int $assistantMessageId, array $result): void
    {
        $this->update([
            'status' => 'completed',
            'assistant_message_id' => $assistantMessageId,
            'total_tokens' => $result['total_tokens'] ?? 0,
            'input_tokens' => $result['input_tokens'] ?? null,
            'output_tokens' => $result['output_tokens'] ?? null,
            'generation_time_ms' => $result['generation_time_ms'] ?? 0,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
