<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\Tenant\Intelligence\AiConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight conversation context for follow-up understanding.
 * Stores only compact structured data — never full conversation history.
 */
class ConversationContext
{
    private const CACHE_PREFIX = 'ai_conv_ctx_';
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Resolve context from conversation + current message.
     */
    public function resolve(AiConversation $conversation, string $currentMessage): array
    {
        $ctx = $this->load($conversation->id);

        // Auto-detect if this looks like a follow-up
        $ctx['is_follow_up'] = $this->isFollowUp($currentMessage, $ctx);

        return $ctx;
    }

    /**
     * Persist context after processing a message.
     */
    public function persist(AiConversation $conversation, array $intent, string $userMessage, string $assistantResponse): void
    {
        $ctx = [
            'last_intent' => $intent['intent'] ?? null,
            'last_category' => $intent['category'] ?? null,
            'last_tool' => $intent['sub_intents'][0] ?? null,
            'last_user_message' => mb_substr($userMessage, 0, 100),
            'last_assistant_response' => mb_substr($assistantResponse, 0, 200),
            'last_updated_at' => now()->toDateTimeString(),
            'message_count' => ($this->load($conversation->id)['message_count'] ?? 0) + 1,
        ];

        $key = self::CACHE_PREFIX . $conversation->id;
        Cache::put($key, $ctx, now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    /**
     * Clear context for a conversation.
     */
    public function clear(int $conversationId): void
    {
        Cache::forget(self::CACHE_PREFIX . $conversationId);
    }

    private function load(int $conversationId): array
    {
        $key = self::CACHE_PREFIX . $conversationId;
        $cached = Cache::get($key);

        if ($cached === null) {
            // Try to infer from recent messages in DB
            return $this->inferFromMessages($conversationId);
        }

        return $cached;
    }

    private function inferFromMessages(int $conversationId): array
    {
        try {
            $conversation = \App\Models\Tenant\Intelligence\AiConversation::find($conversationId);
            if (!$conversation) {
                return $this->emptyContext();
            }

            $recent = $conversation->messages()
                ->whereIn('role', ['user', 'assistant'])
                ->orderBy('id', 'desc')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();

            if ($recent->isEmpty()) {
                return $this->emptyContext();
            }

            $lastUser = $recent->where('role', 'user')->last();
            $lastAssistant = $recent->where('role', 'assistant')->last();

            return [
                'last_intent' => null,
                'last_category' => null,
                'last_tool' => null,
                'last_user_message' => $lastUser ? mb_substr($lastUser->content, 0, 100) : null,
                'last_assistant_response' => $lastAssistant ? mb_substr($lastAssistant->content, 0, 200) : null,
                'last_updated_at' => now()->toDateTimeString(),
                'message_count' => $conversation->messages()->count(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to infer conversation context', ['conv_id' => $conversationId, 'error' => $e->getMessage()]);
            return $this->emptyContext();
        }
    }

    private function emptyContext(): array
    {
        return [
            'last_intent' => null,
            'last_category' => null,
            'last_tool' => null,
            'last_user_message' => null,
            'last_assistant_response' => null,
            'last_updated_at' => null,
            'message_count' => 0,
            'is_follow_up' => false,
        ];
    }

    /**
     * Detect if a message is likely a follow-up.
     */
    private function isFollowUp(string $message, array $ctx): bool
    {
        // No previous context → not a follow-up
        if ($ctx['last_intent'] === null) {
            return false;
        }

        $trimmed = trim($message);

        // Very short messages are likely follow-ups
        if (mb_strlen($trimmed) <= 15) {
            return true;
        }

        // Messages starting with connectors
        $connectors = ['و', 'طيب', 'كمان', 'أكتر', 'أكثر', 'بعدين', 'وبعدين', 'يعني'];
        foreach ($connectors as $conn) {
            if (str_starts_with(mb_strtolower($trimmed), $conn)) {
                return true;
            }
        }

        // Messages with reference words
        $referenceWords = ['ده', 'ده؟', 'اللي فات', 'اللي قبل', 'كمان', 'أيضاً', 'وبعدين'];
        foreach ($referenceWords as $word) {
            if (str_contains(mb_strtolower($trimmed), $word)) {
                return true;
            }
        }

        return false;
    }
}
