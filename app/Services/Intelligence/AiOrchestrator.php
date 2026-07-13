<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\Tenant\Intelligence\AiConversation;
use App\Models\Tenant\Intelligence\AiMessage;
use App\Models\Tenant\Intelligence\AiRun;
use App\Services\Intelligence\Providers\IntelligenceProviderManager;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolExecutor;
use App\Services\Intelligence\Tools\BusinessToolRegistry;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiOrchestrator
{
    private ?IntelligenceProviderManager $providerManager;
    private TenantContext $tenantContext;
    private BusinessToolExecutor $toolExecutor;
    private BusinessToolRegistry $toolRegistry;

    private const AR_CAPABILITY = 'أستطيع حاليًا مساعدتك في تحليل بيانات الأتيليه، مثل الإيرادات والحجوزات والتسليمات والمرتجعات والمخزون وملخص العمل اليومي.' . "\n\n" . 'اختر أحد الأسئلة المقترحة أو اسألني عن جزء محدد من نشاط الأتيليه.';
    private const EN_CAPABILITY = 'I can help you analyze your atelier data: revenue, reservations, deliveries, returns, inventory, and daily summary.' . "\n\n" . 'Choose a suggested question or ask about a specific area.';
    private const AR_FALLBACK = 'تعذر تشغيل التحليل المتقدم مؤقتًا، لكن يمكنني مساعدتك في الاستفسارات المباشرة عن الإيرادات والحجوزات والتسليمات والمرتجعات والمخزون.';
    private const MAX_TOOL_ROUNDS = 3;
    private const MAX_TOOLS_PER_RUN = 6;

    public function __construct(
        ?IntelligenceProviderManager $providerManager,
        TenantContext $tenantContext,
        BusinessToolExecutor $toolExecutor,
    ) {
        $this->providerManager = $providerManager;
        $this->tenantContext = $tenantContext;
        $this->toolExecutor = $toolExecutor;
        $this->toolRegistry = BusinessToolRegistry::withStandardTools();
    }

    public function executeRun(AiRun $run): void
    {
        $run->markProcessing();
        $tenantSlug = $this->tenantContext->slug() ?? 'unknown';

        try {
            $conversation = $run->conversation;
            $conversation->setConnection($run->getConnectionName());
            $userMessage = $run->userMessage;
            $content = $this->sanitizeInput($userMessage->content);
            $user = $userMessage->user;

            // Step 1: Deterministic fast path for clear factual questions
            $deterministicResult = $this->toolExecutor->tryAnswer($user, $content, $tenantSlug);
            if ($deterministicResult['handled'] ?? false) {
                $isComplexQuery = $this->isComplexQuery($content);
                $externalEnabled = config('intelligence.external_enabled', false);

                // If complex query + Groq available → use model for richer response
                if ($isComplexQuery && $externalEnabled && $this->providerManager?->isExternal()) {
                    $this->executeAgenticFlow($run, $conversation, $userMessage, $content, $deterministicResult, $tenantSlug);
                    return;
                }

                // Simple query or no Groq → deterministic response
                $this->saveDeterministicResponse($run, $conversation, $deterministicResult);
                return;
            }

            // Step 2: Unsupported question — check general chat
            if (!$this->isGeneralChatEnabled()) {
                $this->saveCapabilityResponse($run, $conversation, $content);
                return;
            }

            // Step 3: General AI (Groq or local)
            if ($this->providerManager?->isExternal()) {
                $this->executeAgenticFlow($run, $conversation, $userMessage, $content, null, $tenantSlug);
            } else {
                $this->handleGeneralAi($run, $conversation, $userMessage, $tenantSlug);
            }

        } catch (Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error('AI run failed', [
                'run_id' => $run->id,
                'tenant' => $tenantSlug,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Agentic tool-calling flow with Groq.
     */
    private function executeAgenticFlow(
        AiRun $run,
        AiConversation $conversation,
        AiMessage $userMessage,
        string $content,
        ?array $preToolResult,
        string $tenantSlug,
    ): void {
        $provider = $this->providerManager->primary();
        if (!$provider) {
            // Fallback to deterministic
            if ($preToolResult) {
                $this->saveDeterministicResponse($run, $conversation, $preToolResult);
            } else {
                $this->saveFallbackResponse($run, $conversation);
            }
            return;
        }

        $memory = new ConversationMemory($conversation);
        $start = hrtime(true);
        $allToolFacts = [];
        $toolRound = 0;
        $toolsCalled = [];

        // Build initial messages
        $tenant = $this->tenantContext->tenant();
        $systemPrompt = BusinessConstitution::build(
            $tenant?->name ?? 'Atelier',
            $userMessage->user?->name ?? 'User',
            now()->toDateTimeString()
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            $memory->toContextMessage(),
        ];

        // Add recent history (last 6 messages)
        $history = $conversation->messages()
            ->where('id', '<', $userMessage->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get()
            ->sortBy('id');
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        $messages[] = ['role' => 'user', 'content' => $content];

        // Tool schemas
        $tools = ToolSchemaBuilder::all();

        try {
            // Tool-calling loop
            while ($toolRound < self::MAX_TOOL_ROUNDS) {
                $result = $provider->chat($messages, $tools);

                // Check if model requested tool calls
                $toolCalls = $result['tool_calls'] ?? [];
                if ($toolCalls === []) {
                    // Model gave final response
                    break;
                }

                // Execute requested tools
                $toolResults = [];
                foreach ($toolCalls as $toolCall) {
                    if (count($toolsCalled) >= self::MAX_TOOLS_PER_RUN) {
                        break 2;
                    }

                    $function = $toolCall['function'] ?? [];
                    $toolName = $function['name'] ?? '';
                    $args = json_decode($function['arguments'] ?? '{}', true) ?: [];

                    if (!$toolName) {
                        continue;
                    }

                    $context = BusinessToolContext::forUser($userMessage->user, $tenantSlug);
                    $toolResult = $this->toolRegistry->execute($toolName, $context);

                    // Minimize before sending back to model
                    $minimizedFacts = DataMinimizer::minimize($toolResult->facts);
                    $toolResults[] = [
                        'tool_call_id' => $toolCall['id'] ?? '',
                        'role' => 'tool',
                        'name' => $toolName,
                        'content' => json_encode($minimizedFacts, JSON_UNESCAPED_UNICODE),
                    ];

                    $allToolFacts[] = $toolResult->facts;
                    $toolsCalled[] = $toolName;
                    $memory->recordToolUse($toolName);
                }

                // Add assistant's tool request + tool results to messages
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['response'] ?? '',
                    'tool_calls' => $toolCalls,
                ];
                foreach ($toolResults as $tr) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tr['tool_call_id'],
                        'name' => $tr['name'],
                        'content' => $tr['content'],
                    ];
                }

                $toolRound++;
            }

            // Get final response (if we broke from tool loop, get one more)
            if ($toolRound >= self::MAX_TOOL_ROUNDS || !isset($result)) {
                $result = $provider->chat($messages, []);
            }

            $response = $result['response'] ?? '';

            // Validate numerical integrity
            if (!NumericalIntegrityValidator::validate($response, $allToolFacts)) {
                Log::warning('Numerical integrity failed, using deterministic fallback', [
                    'run_id' => $run->id,
                ]);
                if ($preToolResult) {
                    $this->saveDeterministicResponse($run, $conversation, $preToolResult);
                    return;
                }
                $response = self::AR_FALLBACK;
            }

            $elapsedMs = (int) ((hrtime(true) - $start) / 1e6);

            // Save response
            $assistantMessage = $conversation->messages()->create([
                'user_id' => $run->user_id,
                'role' => 'assistant',
                'content' => $response,
                'total_tokens' => $result['total_tokens'] ?? 0,
                'input_tokens' => $result['input_tokens'] ?? 0,
                'output_tokens' => $result['output_tokens'] ?? 0,
                'generation_time_ms' => $elapsedMs,
            ]);

            $run->markCompleted($assistantMessage->id, [
                'input_tokens' => $result['input_tokens'] ?? 0,
                'output_tokens' => $result['output_tokens'] ?? 0,
                'total_tokens' => $result['total_tokens'] ?? 0,
                'generation_time_ms' => $elapsedMs,
                'provider' => $result['provider'] ?? 'unknown',
                'model' => $result['model'] ?? 'unknown',
                'tools_called' => $toolsCalled,
                'tool_rounds' => $toolRound,
            ]);

            // Persist memory
            $memory->persist($conversation);

            Log::info('AI run completed (agentic)', [
                'run_id' => $run->id,
                'tenant' => $tenantSlug,
                'provider' => $result['provider'] ?? 'unknown',
                'model' => $result['model'] ?? 'unknown',
                'tools' => $toolsCalled,
                'rounds' => $toolRound,
                'tokens' => $result['total_tokens'] ?? 0,
                'time_ms' => $elapsedMs,
            ]);

        } catch (Throwable $e) {
            Log::warning('Agentic flow failed, using fallback', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            if ($preToolResult) {
                $this->saveDeterministicResponse($run, $conversation, $preToolResult);
            } else {
                $this->saveFallbackResponse($run, $conversation);
            }
        }
    }

    private function saveDeterministicResponse(AiRun $run, AiConversation $conversation, array $toolResult): void
    {
        $response = $toolResult['response'] ?? '';
        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id,
            'role' => 'assistant',
            'content' => $response,
            'total_tokens' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0,
            'generation_time_ms' => $toolResult['execution_ms'] ?? 0,
            'provider' => 'deterministic',
            'model' => 'none',
        ]);
    }

    private function saveCapabilityResponse(AiRun $run, AiConversation $conversation, string $userMessage): void
    {
        $isArabic = $this->isArabic($userMessage);
        $response = $isArabic ? self::AR_CAPABILITY : self::EN_CAPABILITY;
        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'generation_time_ms' => 0,
        ]);
    }

    private function saveFallbackResponse(AiRun $run, AiConversation $conversation): void
    {
        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => self::AR_FALLBACK,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'generation_time_ms' => 0,
        ]);
    }

    private function handleGeneralAi(AiRun $run, AiConversation $conversation, AiMessage $userMessage, string $tenantSlug): void
    {
        // Local model path — kept for compatibility
        $client = new DressnMoreAiClient();
        $messages = $this->buildMessages($conversation, $userMessage);
        $result = $client->generate($messages, [
            'temperature' => config('intelligence.generation.temperature', 0.7),
            'max_tokens' => config('intelligence.generation.default_output_tokens', 96),
        ]);

        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $result['response'],
            'total_tokens' => $result['total_tokens'], 'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'], 'generation_time_ms' => $result['generation_time_ms'],
        ]);
        $run->markCompleted($assistantMessage->id, $result);
    }

    private function isComplexQuery(string $content): bool
    {
        $complexIndicators = [
            'قارن', 'compare', 'مقارنة', 'why', 'ليه', 'ezay', 'إزاي', 'سبب', 'سبب',
            'analysis', 'تحليل', 'اعمل ايه', 'أعمل إيه', 'نصيحة', 'recommend',
            'attention', 'انتباه', 'first', 'أول', 'focus', 'تركيز', 'problem', 'مشكلة',
            'better', 'أحسن', 'worse', 'أسوأ', 'decline', 'تراجع', 'improve', 'تحسن',
            'ضعيف', 'ضعيفة', 'strong', 'قوي', 'should i', 'هل لازم',
        ];
        $lower = mb_strtolower($content);
        foreach ($complexIndicators as $indicator) {
            if (str_contains($lower, $indicator)) {
                return true;
            }
        }
        return false;
    }

    private function isGeneralChatEnabled(): bool
    {
        return config('intelligence.features.general_chat_enabled', false);
    }

    private function isArabic(string $text): bool
    {
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars = mb_strlen(preg_replace('/\s/', '', $text)) ?: 1;
        return ($arabicCount / $totalChars) > 0.3;
    }

    private function sanitizeInput(string $input): string
    {
        $maxChars = config('intelligence.limits.max_input_chars', 1500);
        $input = trim($input);
        if ($input === '') {
            throw new \RuntimeException('Message content cannot be empty.');
        }
        if (mb_strlen($input) > $maxChars) {
            $input = mb_substr($input, 0, $maxChars);
        }
        $input = str_replace("\0", '', $input);
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input) ?? $input;
        return $input;
    }

    private function buildMessages(AiConversation $conversation, AiMessage $userMessage): array
    {
        $tenant = $this->tenantContext->tenant();
        $tenantName = $tenant?->name ?? 'Unknown';
        $userName = $userMessage->user?->name ?? 'User';
        $today = now()->toDateTimeString();

        $systemPrompt = <<<PROMPT
You are DressnMore Intelligence for {$tenantName}. Current user: {$userName}. Date: {$today}.
Keep responses under 150 words. Speak the user's language (Arabic or English). Be professional and concise.
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
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
        $messages[] = ['role' => 'user', 'content' => $this->sanitizeInput($userMessage->content)];
        return $messages;
    }
}
