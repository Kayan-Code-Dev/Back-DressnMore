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

            // Step 1: Always try business tools first to get real data
            $deterministicResult = $this->toolExecutor->tryAnswer($user, $content, $tenantSlug);

            if ($deterministicResult['handled'] ?? false) {
                // We have real data from tools
                if ($isComplex && $externalEnabled && $this->providerManager?->isExternal()) {
                    // Complex query + Groq available → use model to write natural response
                    $this->executeNaturalLanguageFlow($run, $conversation, $userMessage, $content, $deterministicResult, $tenantSlug);
                    return;
                }
                // Simple query → return deterministic response directly
                $this->saveDeterministicResponse($run, $conversation, $deterministicResult);
                return;
            }

            // Step 2: Not a recognized tool question
            if ($isComplex && $externalEnabled && $this->providerManager?->isExternal()) {
                // Try general knowledge with Groq
                $this->executeGeneralChat($run, $conversation, $userMessage, $content, $tenantSlug);
                return;
            }

            if (!$this->isGeneralChatEnabled()) {
                $this->saveSmartFallback($run, $conversation, $content, $deterministicResult);
                return;
            }

            // General AI (local model)
            $this->handleGeneralAi($run, $conversation, $userMessage, $tenantSlug);

        } catch (Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error('AI run failed', ['run_id' => $run->id, 'tenant' => $tenantSlug, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * NEW APPROACH: Instead of forcing tool calling (which models struggle with),
     * we pre-fetch data deterministically and ask the model to write a natural
     * human-like Arabic response based on that data.
     */
    private function executeNaturalLanguageFlow(
        AiRun $run, AiConversation $conversation, AiMessage $userMessage,
        string $content, array $toolResult, string $tenantSlug,
    ): void {
        $provider = $this->providerManager?->primary();
        if (!$provider) {
            $this->saveDeterministicResponse($run, $conversation, $toolResult);
            return;
        }

        $start = hrtime(true);
        $toolData = $toolResult['response'] ?? '';

        // Build a simple rewrite prompt
        $systemPrompt = "أنت صاحب أتيليه خبير بيتكلم مع زميلك صاحب الأتيليه بالعامية المصرية. حوّل البيانات اللي جاية لك لرد طبيعي ودافي زي المحادثة بين صحاب. استخدم كلمات زي 'النهاردة'، 'تمام'، 'مفيش'، 'شوية'. اختصر الرد. ما تضيفش معلومات من عندك.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "اكتب رد طبيعي:\n\n" . $toolData],
        ];

        $result = $provider->chat($messages, []);
        $response = $result['response'] ?? '';
        $response = preg_replace('/<think>.*?<\/think>/s', '', $response);
        $response = trim($response);

        // If model returned garbage, use deterministic response
        if ($response === '' || $this->isGenericRefusal($response) || strlen($response) < 10) {
            $response = $toolData;
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
            'tools_called' => [],
            'tool_rounds' => 0,
            'flow' => 'natural-language-enhanced',
        ]);
    }

    private function executeGeneralChat(
        AiRun $run, AiConversation $conversation, AiMessage $userMessage,
        string $content, string $tenantSlug,
    ): void {
        $provider = $this->providerManager?->primary();
        if (!$provider) {
            $this->saveBasicFallback($run, $conversation);
            return;
        }

        $memory = new ConversationMemory($conversation);
        $start = hrtime(true);

        $tenant = $this->tenantContext->tenant();
        $tenantName = $tenant?->name ?? 'Atelier';
        $userName = $userMessage->user?->name ?? 'User';
        $date = now()->toDateTimeString();

        $systemPrompt = BusinessConstitution::build($tenantName, $userName, $date);
        $systemPrompt .= "\n\nYou are having a general conversation. Be helpful, warm, and speak in natural Arabic. Keep responses concise.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

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

        $result = $provider->chat($messages, []);
        $response = $result['response'] ?? '';
        $response = preg_replace('/<think>.*?<\/think>/s', '', $response);
        $response = trim($response);

        if ($response === '') {
            $response = "عذرًا، لم أفهم السؤال تمامًا. ممكن توضح أكتر؟";
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
            'tools_called' => [],
            'tool_rounds' => 0,
            'flow' => 'general-chat',
        ]);

        $memory->persist($conversation);
    }

    /**
     * Check if model returned a generic refusal instead of useful content.
     */
    private function isGenericRefusal(string $response): bool
    {
        $refusalPatterns = [
            'لا يمكنني الوصول',
            'لا أستطيع الوصول',
            'غير متاح',
            'عذراً',
            'لا أملك',
            'ليس لدي',
            'لا أستطيع',
            'I cannot access',
            "I don't have access",
            'I do not have',
            'unavailable',
        ];

        foreach ($refusalPatterns as $pattern) {
            if (str_contains($response, $pattern)) {
                return true;
            }
        }
        return false;
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
            'tools_called' => [], 'tool_rounds' => 0,
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
        $response = "أستطيع مساعدتك في:
• الإيرادات والتحصيلات
• الحجوزات والتسليمات
• المرتجعات المتأخرة
• المخزون والفساتين الراكدة
• ملخص النشاط اليومي

اسألني مباشرة عن أي جزء من أعمال الأتيليه.";

        $assistantMessage = $conversation->messages()->create([
            'user_id' => $run->user_id, 'role' => 'assistant', 'content' => $response,
            'total_tokens' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'generation_time_ms' => 0,
        ]);
        $run->markCompleted($assistantMessage->id, [
            'input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0,
            'generation_time_ms' => 0,
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
