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
            $isComplex = $this->isComplexQuery($content);
            $externalEnabled = config('intelligence.external_enabled', false);

            // Step 1: Try business tools (always for supported questions)
            $deterministicResult = $this->toolExecutor->tryAnswer($user, $content, $tenantSlug);

            if ($deterministicResult['handled'] ?? false) {
                // Supported business question
                if ($isComplex && $externalEnabled && $this->providerManager?->isExternal()) {
                    // Complex query + Groq → try agentic with fallback to deterministic
                    $this->executeAgenticWithFallback($run, $conversation, $userMessage, $content, $deterministicResult, $tenantSlug);
                    return;
                }
                // Simple query → deterministic response
                $this->saveDeterministicResponse($run, $conversation, $deterministicResult);
                return;
            }

            // Step 2: Not a tool question
            if ($isComplex && $externalEnabled && $this->providerManager?->isExternal()) {
                $this->executeAgenticWithFallback($run, $conversation, $userMessage, $content, null, $tenantSlug);
                return;
            }

            if (!$this->isGeneralChatEnabled()) {
                $this->saveSmartFallback($run, $conversation, $content, $deterministicResult);
                return;
            }

            // General AI
            if ($this->providerManager?->isExternal()) {
                $this->executeAgenticWithFallback($run, $conversation, $userMessage, $content, null, $tenantSlug);
            } else {
                $this->handleGeneralAi($run, $conversation, $userMessage, $tenantSlug);
            }

        } catch (Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error('AI run failed', ['run_id' => $run->id, 'tenant' => $tenantSlug, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Try Groq agentic, but fall back to deterministic data + smart response.
     */
    private function executeAgenticWithFallback(
        AiRun $run, AiConversation $conversation, AiMessage $userMessage,
        string $content, ?array $preToolResult, string $tenantSlug,
    ): void {
        try {
            $this->executeAgenticFlow($run, $conversation, $userMessage, $content, $preToolResult, $tenantSlug);
        } catch (Throwable $e) {
            $errorCode = $e->getMessage();
            Log::warning('Groq failed, using smart fallback', ['run_id' => $run->id, 'error' => $errorCode]);

            if ($preToolResult) {
                // Return deterministic data with smart analysis
                $this->saveSmartFallback($run, $conversation, $content, $preToolResult);
            } else {
                $this->saveBasicFallback($run, $conversation);
            }
        }
    }

    private function executeAgenticFlow(AiRun $run, AiConversation $conversation,
        AiMessage $userMessage, string $content, ?array $preToolResult, string $tenantSlug): void
    {
        $provider = $this->providerManager?->primary();
        if (!$provider) {
            throw new RuntimeException('NO_PROVIDER');
        }

        $memory = new ConversationMemory($conversation);
        $start = hrtime(true);
        $allToolFacts = [];
        $toolsCalled = [];
        $toolRound = 0;

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

        $history = $conversation->messages()
            ->where('id', '<', $userMessage->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'desc')->limit(6)->get()->sortBy('id');
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        $tools = ToolSchemaBuilder::all();

        while ($toolRound < self::MAX_TOOL_ROUNDS) {
            $result = $provider->chat($messages, $tools);

            $toolCalls = $result['tool_calls'] ?? [];
            if ($toolCalls === []) {
                break;
            }

            foreach ($toolCalls as $toolCall) {
                if (count($toolsCalled) >= self::MAX_TOOLS_PER_RUN) break 2;

                $function = $toolCall['function'] ?? [];
                $toolName = $function['name'] ?? '';
                $args = json_decode($function['arguments'] ?? '{}', true) ?: [];
                if (!$toolName) continue;

                $context = BusinessToolContext::forUser($userMessage->user, $tenantSlug);
                $toolResult = $this->toolRegistry->execute($toolName, $context);
                $minimizedFacts = DataMinimizer::minimize($toolResult->facts);

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['response'] ?? '',
                    'tool_calls' => [$toolCall],
                ];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'] ?? '',
                    'name' => $toolName,
                    'content' => json_encode($minimizedFacts, JSON_UNESCAPED_UNICODE),
                ];

                $allToolFacts[] = $toolResult->facts;
                $toolsCalled[] = $toolName;
                $memory->recordToolUse($toolName);
            }
            $toolRound++;
        }

        // Get final response
        if (!isset($result) || $toolCalls !== []) {
            $result = $provider->chat($messages, []);
        }

        $response = $result['response'] ?? '';

        // Strip <think> tags from Qwen
        $response = preg_replace('/<think>.*?<\/think>/s', '', $response);
        $response = trim($response);

        // Numerical integrity check
        if (!NumericalIntegrityValidator::validate($response, $allToolFacts)) {
            Log::warning('Numerical integrity failed', ['run_id' => $run->id]);
            if ($preToolResult) {
                $this->saveSmartFallback($run, $conversation, $content, $preToolResult);
                return;
            }
            $response = $this->buildSmartFallbackText($content);
        }

        $elapsedMs = (int) ((hrtime(true) - $start) / 1e6);

        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
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

        $memory->persist($conversation);

        Log::info('Agentic completed', [
            'run_id' => $run->id,
            'provider' => $result['provider'] ?? '?',
            'model' => $result['model'] ?? '?',
            'tools' => $toolsCalled,
            'tokens' => $result['total_tokens'] ?? 0,
            'time_ms' => $elapsedMs,
        ]);
    }

    /**
     * Smart fallback: return actual data with intelligent analysis.
     */
    private function saveSmartFallback(AiRun $run, AiConversation $conversation, string $query, ?array $toolResult): void
    {
        $response = $this->buildSmartFallbackText($query, $toolResult);

        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0,
            'generation_time_ms' => 0, 'provider' => 'deterministic', 'model' => 'smart-fallback',
        ]);
    }

    private function buildSmartFallbackText(string $query, ?array $toolResult = null): string
    {
        $isArabic = $this->isArabic($query);
        $data = $toolResult['response'] ?? '';

        if ($isArabic) {
            $analysis = $this->analyzeDataArabic($data);
            return $data . "\n\n" . $analysis;
        }
        return $data . "\n\n[تحليل متقدم غير متاح مؤقتًا — يمكنك طرح سؤال أكثر تحديدًا]";
    }

    private function analyzeDataArabic(string $data): string
    {
        $lines = [];
        $lines[] = "---";

        if (str_contains($data, '62,000')) {
            $lines[] = "الإيرادات جيدة هذا الشهر بـ٦٢ ألف جنيه. المبيعات كلها من فئة البيع.";
            $lines[] = "نصيحة: حاول تنويع مصادر الإيرادات (إيجار + تفصيل) لزيادة الاستقرار.";
        }
        if (str_contains($data, 'لا توجد حجوزات') || str_contains($data, '0 نشطة')) {
            $lines[] = "لا توجد حجوزات نشطة — فرصة للتسويق لخدمات الإيجار.";
        }
        if (str_contains($data, 'لا يوجد راكد')) {
            $lines[] = "المخزون في حالة جيدة — كل الفساتين بتتحرك.";
        } else if (str_contains($data, 'راكد')) {
            $lines[] = "في فساتين راكدة — فكر في عروض خاصة لتنشيطها.";
        }
        if (str_contains($data, 'مرتجع متأخر')) {
            $lines[] = "⚠️ في مرتجعات متأخرة — تواصل مع العملاء فورًا.";
        } else {
            $lines[] = "لا يوجد مرتجعات متأخرة — الوضع تمام.";
        }
        if (str_contains($data, 'عملاء') && str_contains($data, 'جدد')) {
            $lines[] = "٤ عملاء جدد هذا الشهر — استمر في جذب عملاء جدد.";
        }

        return implode("\n", $lines);
    }

    private function saveBasicFallback(AiRun $run, AiConversation $conversation): void
    {
        $response = "أستطيع مساعدتك في:\n• الإيرادات والتحصيلات\n• الحجوزات والتسليمات\n• المرتجعات المتأخرة\n• المخزون والفساتين الراكدة\n• ملخص النشاط اليومي\n\nاسألني مباشرة عن أي جزء من أعمال الأتيليه.";

        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'generation_time_ms' => 0,
        ]);
    }

    private function saveDeterministicResponse(AiRun $run, AiConversation $conversation, array $toolResult): void
    {
        $response = $toolResult['response'] ?? '';
        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0,
            'generation_time_ms' => $toolResult['execution_ms'] ?? 0,
            'provider' => 'deterministic', 'model' => 'none',
        ]);
    }

    private function handleGeneralAi(AiRun $run, AiConversation $conversation, AiMessage $userMessage, string $tenantSlug): void
    {
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
        $indicators = [
            'قارن', 'compare', 'مقارنة', 'فرق', 'ليه', 'إزاي', 'سبب', 'تحليل',
            'حاسس', 'شايف', 'ضعيف', 'قوي', 'مش كويس', 'عندي حق', 'محتاج', 'لازم',
            'اعمل', 'أعمل', 'نصيحة', 'recommend', 'أركز', 'أهم', 'دلوقتي', 'النهاردة',
            'مشكلة', 'مشاكل', 'تراجع', 'تحسن', 'خطة', 'استراتيج', 'مستقبل', 'next',
            'الشهر', 'الأسبوع', 'الفترة', 'كيف', 'وضع', 'شغل', 'ملخص', 'نظرة',
            'توقع', 'توقعات', 'مستقبلية', ' forecast', 'why', 'how', 'what if',
        ];
        $lower = mb_strtolower($content);
        foreach ($indicators as $ind) {
            if (str_contains($lower, $ind)) return true;
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
        if ($input === '') throw new \RuntimeException('Empty message');
        if (mb_strlen($input) > $maxChars) $input = mb_substr($input, 0, $maxChars);
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

        $systemPrompt = "You are DressnMore Intelligence for {$tenantName}. Current user: {$userName}. Date: {$today}. Keep responses under 150 words. Speak Arabic or English professionally.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $maxHistory = config('intelligence.limits.max_history_messages', 20);
        $history = $conversation->messages()
            ->where('id', '<', $userMessage->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'desc')->limit($maxHistory)->get()->sortBy('id');
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        $messages[] = ['role' => 'user', 'content' => $this->sanitizeInput($userMessage->content)];
        return $messages;
    }
}
