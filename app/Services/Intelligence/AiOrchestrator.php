<?php

namespace App\Services\Intelligence;

use App\Models\Tenant\Intelligence\AiConversation;
use App\Models\Tenant\Intelligence\AiMessage;
use App\Models\Tenant\Intelligence\AiRun;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiOrchestrator
{
    private DressnMoreAiClient $client;
    private TenantContext $tenantContext;

    private const SYSTEM_PROMPT_TEMPLATE = <<<'PROMPT'
You are **DressnMore Intelligence**, the built-in AI assistant for DressnMore — a multi-tenant SaaS ERP designed for ateliers, dress rental shops, and tailoring businesses.

Your core responsibilities:
1. **Business Analysis** — Help users understand their data: sales trends, inventory levels, customer patterns, financial summaries.
2. **Operational Guidance** — Explain ERP features, workflows (rental, tailoring, delivery, returns), and best practices.
3. **Decision Support** — Provide actionable recommendations based on the context shared with you.
4. **Data Safety** — Never expose data from other tenants or users. Only reference the current tenant's context.

Personality: Professional, warm, concise, and precise. You speak the user's language (Arabic or English). Keep responses under 150 words unless detailed analysis is requested.

Current tenant: %s
Current user: %s
Date: %s
PROMPT;

    public function __construct(DressnMoreAiClient $client, TenantContext $tenantContext)
    {
        $this->client = $client;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Execute an AI chat run asynchronously.
     */
    public function executeRun(AiRun $run): void
    {
        $run->markProcessing();

        try {
            $conversation = $run->conversation;
            $userMessage = $run->userMessage;

            // Build message history
            $messages = $this->buildMessages($conversation, $userMessage);

            // Call AI service
            $result = $this->client->generate($messages, [
                'temperature' => 0.7,
                'max_tokens' => 160,
            ]);

            // Save assistant response
            $assistantMessage = $conversation->messages()->create([
                'user_id' => $run->user_id,
                'role' => 'assistant',
                'content' => $result['response'],
                'tokens_used' => $result['tokens_used'],
                'generation_time_ms' => $result['generation_time_ms'],
            ]);

            $run->markCompleted($assistantMessage->id, $result);

            Log::info('AI run completed', [
                'run_id' => $run->id,
                'tenant' => $this->tenantContext->slug(),
                'tokens' => $result['tokens_used'],
                'time_ms' => $result['generation_time_ms'],
            ]);
        } catch (Throwable $e) {
            $run->markFailed($e->getMessage());

            Log::error('AI run failed', [
                'run_id' => $run->id,
                'tenant' => $this->tenantContext->slug(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the complete message array for the AI, including system prompt and history.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(AiConversation $conversation, AiMessage $userMessage): array
    {
        $tenant = $this->tenantContext->tenant();
        $tenantName = $tenant?->name ?? 'Unknown';
        $userName = $userMessage->user?->name ?? 'User';
        $today = now()->toDateTimeString();

        $systemPrompt = sprintf(self::SYSTEM_PROMPT_TEMPLATE, $tenantName, $userName, $today);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history (recent messages first, up to limit)
        $maxHistory = config('intelligence.limits.max_history_messages', 20);
        $history = $conversation->messages()
            ->where('id', '<', $userMessage->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'desc')
            ->limit($maxHistory)
            ->get()
            ->sortBy('id');

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        // Add the current user message
        $messages[] = ['role' => 'user', 'content' => $this->sanitizeInput($userMessage->content)];

        return $messages;
    }

    /**
     * Sanitize user input before sending to AI.
     */
    private function sanitizeInput(string $input): string
    {
        // Trim and limit length
        $maxChars = config('intelligence.limits.max_input_chars', 2000);
        $input = trim($input);
        if (mb_strlen($input) > $maxChars) {
            $input = mb_substr($input, 0, $maxChars);
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Remove control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input) ?? $input;

        return $input;
    }
}
