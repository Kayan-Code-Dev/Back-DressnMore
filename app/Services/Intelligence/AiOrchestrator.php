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

/**
 * DressnMore Intelligence Orchestrator
 * 
 * Pipeline: User message → Intent → Context → Tools → Template response
 *            → (Optional: Model polish for small talk) → Save → Return
 */
class AiOrchestrator
{
    private TenantContext $tenantContext;
    private BusinessToolExecutor $toolExecutor;
    private BusinessToolRegistry $toolRegistry;
    private DressnMoreAiClient $aiClient;
    private ?IntelligenceProviderManager $providerManager;
    private ConversationContext $conversationContext;

    // Max time to wait for model (ms)
    private const MODEL_TIMEOUT_MS = 8000;
    // Max tools per run
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
        $this->aiClient = new DressnMoreAiClient();
        $this->conversationContext = new ConversationContext();
    }

    /**
     * Main entry point. Executes a single AI run for a user message.
     */
    public function executeRun(AiRun $run): void
    {
        $run->markProcessing();
        $tenantSlug = $this->tenantContext->slug() ?? 'unknown';
        $startTime = hrtime(true);

        try {
            $conversation = $run->conversation;
            $conversation->setConnection($run->getConnectionName());
            $userMessage = $run->userMessage;
            $content = $this->sanitizeInput($userMessage->content);
            $user = $userMessage->user;

            // ─── Stage 1: Resolve conversation context ───
            $ctx = $this->conversationContext->resolve($conversation, $content);

            // ─── Stage 2: Classify intent ───
            $intent = $this->classifyIntent($content, $ctx);
            Log::info('Intent classified', [
                'run_id' => $run->id,
                'intent' => $intent['intent'],
                'confidence' => $intent['confidence'],
            ]);

            // ─── Stage 3: Route to handler ───
            $response = match ($intent['intent']) {
                'small_talk' => $this->handleSmallTalk($content, $intent, $ctx),
                'business_data' => $this->handleBusinessData($content, $intent, $ctx, $user, $tenantSlug, $run),
                'comparison' => $this->handleComparison($content, $intent, $ctx, $user, $tenantSlug, $run),
                'decision_support' => $this->handleDecisionSupport($content, $intent, $ctx, $user, $tenantSlug, $run),
                'ambiguous' => $this->handleAmbiguous($content, $intent, $ctx),
                'unsupported' => $this->handleUnsupported($content, $intent, $ctx),
                default => $this->handleBusinessData($content, $intent, $ctx, $user, $tenantSlug, $run),
            };

            // ─── Stage 4: Sanitize response ───
            $response = $this->sanitizeResponse($response);

            // ─── Stage 5: Persist ───
            $elapsedMs = (int) ((hrtime(true) - $startTime) / 1e6);
            $assistantMessage = $conversation->messages()->create([
                'user_id' => $run->user_id,
                'role' => 'assistant',
                'content' => $response,
                'total_tokens' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'generation_time_ms' => $elapsedMs,
            ]);

            $run->markCompleted($assistantMessage->id, [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'generation_time_ms' => $elapsedMs,
                'provider' => 'deterministic',
                'model' => 'none',
                'intent' => $intent['intent'],
                'confidence' => $intent['confidence'],
                'flow' => 'template-based',
            ]);

            // ─── Stage 6: Update conversation context ───
            $this->conversationContext->persist($conversation, $intent, $content, $response);

            Log::info('Run completed', [
                'run_id' => $run->id,
                'intent' => $intent['intent'],
                'time_ms' => $elapsedMs,
            ]);

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

    // ═══════════════════════════════════════════════════════════════
    // INTENT CLASSIFICATION
    // ═══════════════════════════════════════════════════════════════

    private function classifyIntent(string $content, array $ctx): array
    {
        $lower = mb_strtolower($content);

        // ─── 1. Small talk (highest priority, never call tools) ───
        $smallTalkPatterns = [
            'مرحب', 'السلام عليك', 'صباح', 'مساء', 'أهلا', 'أهلين', 'هلا',
            'هاي', 'حياك', 'أهلاً', 'أهلا وسهلا', 'تصبح', 'تصبحي',
            'كيف الحال', 'كيفك', 'إزيك', 'أخبارك', 'أخبارك إيه',
            'شكرا', 'شكراً', 'يسلمو', 'تسلم', 'مشكور', 'الله يخليك',
            'باي', 'مع السلامة', 'سلام', 'إلى اللقاء', 'بأمان الله',
            'مين انت', 'مين أنت', 'انت مين', 'إنت مين', 'بتعمل إيه',
            'شو بتقدر تساعدني', 'شو بتقدر', 'بتساعد في ايه',
            'بتعمل ايه', 'وظيفتك ايه', 'شو بتسوي',
        ];
        foreach ($smallTalkPatterns as $pattern) {
            if (str_contains($lower, mb_strtolower($pattern))) {
                return ['intent' => 'small_talk', 'confidence' => 0.99, 'category' => 'greeting'];
            }
        }

        // ─── 2. Decision support ───
        $decisionPatterns = [
            'ساعدني باتخاذ قرار', 'ساعدني بقرار', 'أعمل خصم', 'أشتري فساتين',
            'أزود موظفين', 'أفتح فرع', 'أوقف نوع', 'أزود سعر', 'أقلل سعر',
            'أغير', 'أعدل', 'نصيحة', 'تنصحني', 'تنصحني', 'توصي',
            'قرار', 'قراري', 'القرار', 'أعمل ولا', 'ولا لا',
        ];
        foreach ($decisionPatterns as $pattern) {
            if (str_contains($lower, mb_strtolower($pattern))) {
                return ['intent' => 'decision_support', 'confidence' => 0.92, 'category' => 'decision'];
            }
        }

        // ─── 3. Comparison ───
        $comparisonPatterns = [
            'قارن', 'مقارنة', 'أحسن ولا', 'أفضل ولا', 'زادت ولا', 'قلت ولا',
            'فرق بين', 'الفرق بين', 'مقارنة بين', 'versus', 'vs',
            'الشهر ده أحسن', 'الشهر اللي فات', 'الأسبوع الماضي',
            'زيادة عن', 'نقصان عن', 'ارتفعت', 'انخفضت',
        ];
        foreach ($comparisonPatterns as $pattern) {
            if (str_contains($lower, mb_strtolower($pattern))) {
                return ['intent' => 'comparison', 'confidence' => 0.90, 'category' => 'comparison'];
            }
        }

        // ─── 4. Follow-up context ───
        if ($ctx['last_intent'] !== null) {
            $followUpPatterns = [
                'والشهر اللي فات', 'والفترة اللي فاتت', 'واللي قبله',
                'طيب وليه', 'وليه', 'ليه كده', 'إزاي',
                'خزنة الفرع', 'الفرع الرئيسي', 'فرع بس',
                'المصاريف', 'والمصاريف', 'والتكاليف',
            ];
            foreach ($followUpPatterns as $pattern) {
                if (str_contains($lower, mb_strtolower($pattern))) {
                    // Map follow-up to comparison if asking about previous period
                    if (str_contains($lower, 'الشهر اللي فات') || str_contains($lower, 'الفترة اللي فاتت') || str_contains($lower, 'واللي قبله')) {
                        return ['intent' => 'comparison', 'confidence' => 0.88, 'category' => 'follow_up', 'base_intent' => $ctx['last_intent']];
                    }
                    // Map to business data with context
                    return ['intent' => 'business_data', 'confidence' => 0.85, 'category' => 'follow_up', 'base_intent' => $ctx['last_intent']];
                }
            }

            // Generic follow-up: "ليه؟" "و؟" "اكتر"
            if (in_array(trim($content), ['ليه؟', 'ليه', 'و؟', 'اكتر', 'أكتر', 'كمان', 'كمان؟', 'طيب؟', 'طيب'])) {
                return ['intent' => 'business_data', 'confidence' => 0.80, 'category' => 'follow_up', 'base_intent' => $ctx['last_intent']];
            }
        }

        // ─── 5. Business data (route to BusinessQuestionRouter) ───
        $router = new Tools\BusinessQuestionRouter();
        $intents = $router->route($content);
        if ($intents !== []) {
            return ['intent' => 'business_data', 'confidence' => 0.85, 'category' => 'business', 'sub_intents' => $intents];
        }

        // ─── 6. Check if ambiguous ───
        if (mb_strlen($content) < 5 || count(array_filter(explode(' ', $content))) < 2) {
            return ['intent' => 'ambiguous', 'confidence' => 0.70, 'category' => 'ambiguous'];
        }

        // ─── 7. Unsupported ───
        return ['intent' => 'unsupported', 'confidence' => 0.95, 'category' => 'unsupported'];
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS
    // ═══════════════════════════════════════════════════════════════

    private function handleSmallTalk(string $content, array $intent, array $ctx): string
    {
        $lower = mb_strtolower($content);

        // Greetings
        if (str_contains($lower, 'مرحب') || str_contains($lower, 'السلام عليك') || str_contains($lower, 'أهلا') || str_contains($lower, 'هاي') || str_contains($lower, 'هلا') || str_contains($lower, 'حياك')) {
            return 'أهلًا وسهلًا! 👋 أنا مستشار DressnMore الذكي. كيف أقدر أساعدك في شغل الأتيليه اليوم؟';
        }
        if (str_contains($lower, 'صباح') || str_contains($lower, 'صباحو')) {
            return 'صباح الخير! ☀️ جاهز أساعدك في أي استفسار عن إيراداتك، حجوزاتك، أو مخزونك.';
        }
        if (str_contains($lower, 'مساء')) {
            return 'مساء الخير! 🌙 شلون أقدر أساعدك اليوم؟';
        }

        // Thanks
        if (str_contains($lower, 'شكر') || str_contains($lower, 'يسلمو') || str_contains($lower, 'تسلم') || str_contains($lower, 'مشكور') || str_contains($lower, 'الله يخليك')) {
            return 'العفو! 😊 في أي وقت. لو محتاج حاجة تانية، أنا جاهز.';
        }

        // Goodbye
        if (str_contains($lower, 'مع السلامة') || str_contains($lower, 'باي') || str_contains($lower, 'إلى اللقاء') || str_contains($lower, 'بأمان')) {
            return 'مع السلامة! 🙏 لو احتجتني، أنا موجود.';
        }

        // Identity
        if (str_contains($lower, 'مين انت') || str_contains($lower, 'إنت مين') || str_contains($lower, 'بتعمل إيه') || str_contains($lower, 'شو بتقدر') || str_contains($lower, 'وظيفتك')) {
            return 'أنا مستشار DressnMore الذكي، متخصص في مساعدة أصحاب الأتيليهات.

أقدر أساعدك في:
• إيراداتك والتحصيلات
• حجوزات الإيجار والتسليمات
• المخزون والفساتين الراكدة
• المرتجعات المتأخرة
• مقارنة الفترات
• دعم قراراتك التشغيلية

إزاي أقدر أساعدك؟';
        }

        // How are you
        if (str_contains($lower, 'كيف الحال') || str_contains($lower, 'كيفك') || str_contains($lower, 'إزيك') || str_contains($lower, 'أخبارك')) {
            return 'تمام الحمد لله! 🙌 أنا جاهز أساعدك. عندك أي استفسار عن الأتيليه؟';
        }

        // Fallback greeting
        return 'أهلًا! 👋 أنا مستشار DressnMore. كيف أقدر أساعدك في شغل الأتيليه اليوم؟';
    }

    private function handleBusinessData(string $content, array $intent, array $ctx, $user, string $tenantSlug, AiRun $run): string
    {
        // Execute tools
        $deterministicResult = $this->toolExecutor->tryAnswer($user, $content, $tenantSlug);

        if (!($deterministicResult['handled'] ?? false)) {
            // Not a recognized business question → treat as unsupported
            return $this->handleUnsupported($content, $intent, $ctx);
        }

        $response = $deterministicResult['response'] ?? '';

        // If we have a model-intent context for follow-up, enhance the response
        if ($ctx['last_intent'] === 'business_data' && $ctx['last_tool']) {
            // This is a follow-up — the response is already contextual from the router
        }

        return $response;
    }

    private function handleComparison(string $content, array $intent, array $ctx, $user, string $tenantSlug, AiRun $run): string
    {
        $lower = mb_strtolower($content);

        // Determine what to compare based on context
        $compareWhat = 'revenue'; // default
        if ($ctx['last_intent'] === 'business_data' && $ctx['last_tool']) {
            $compareWhat = $ctx['last_tool'];
        }
        if (str_contains($lower, 'مصاريف') || str_contains($lower, 'تكاليف')) {
            $compareWhat = 'expenses';
        }
        if (str_contains($lower, 'حجز') || str_contains($lower, 'ايجار')) {
            $compareWhat = 'reservations';
        }

        // Get current period data
        $deterministicResult = $this->toolExecutor->tryAnswer($user, $content, $tenantSlug);

        if (!($deterministicResult['handled'] ?? false)) {
            return $this->buildComparisonFromContext($content, $ctx);
        }

        $response = $deterministicResult['response'] ?? '';

        // Add comparison context if we have previous period data in facts
        $facts = $deterministicResult['facts'] ?? [];
        foreach ($facts as $factSet) {
            if (isset($factSet['previous_period_revenue']) && isset($factSet['total_revenue'])) {
                $prev = $factSet['previous_period_revenue'];
                $curr = $factSet['total_revenue'];
                $change = $factSet['change_percent'] ?? 0;
                $direction = $change >= 0 ? 'أعلى' : 'أقل';
                $changeAbs = abs($change);
                $response .= "\n\n📊 مقارنة بالفترة السابقة:\n";
                $response .= "الفترة السابقة: " . number_format($prev) . " جنيه\n";
                $response .= "التغير: {$changeAbs}% {$direction}";
            }
        }

        return $response;
    }

    private function handleDecisionSupport(string $content, array $intent, array $ctx, $user, string $tenantSlug, AiRun $run): string
    {
        $lower = mb_strtolower($content);

        // Specific decision: discount
        if (str_contains($lower, 'خصم')) {
            return $this->decisionDiscount($user, $tenantSlug);
        }

        // Specific decision: hire employees
        if (str_contains($lower, 'موظف') || str_contains($lower, 'موظفين')) {
            return $this->decisionHire($user, $tenantSlug);
        }

        // Specific decision: buy dresses
        if (str_contains($lower, 'اشتري') || str_contains($lower, 'فستان') || str_contains($lower, 'فساتين')) {
            return $this->decisionInventory($user, $tenantSlug);
        }

        // Specific decision: open branch
        if (str_contains($lower, 'فرع')) {
            return $this->decisionBranch($user, $tenantSlug);
        }

        // Generic decision → ask for clarification
        return "أكيد. القرار بخصوص إيه تحديدًا؟ 🤔\n\nممكن يكون:
• الأسعار (خصم/زيادة)
• الموظفين (تعيين/تقليل)
• المخزون (شراء/تصفية)
• التسويق والإعلان
• التوسع (فرع جديد)
\nأوصف موقفك وأنا أساعدك بالأرقام.";
    }

    private function handleAmbiguous(string $content, array $intent, array $ctx): string
    {
        return "عذرًا، مش فاهم سؤالك تمامًا. 🙏\n\nممكن توضح أكتر؟ مثلاً:
• إيرادات الشهر كام؟
• عندي مرتجعات متأخرة؟
• وضع المخزون إيه؟";
    }

    private function handleUnsupported(string $content, array $intent, array $ctx): string
    {
        $lower = mb_strtolower($content);

        // Check if it's a cashbox question (missing tool)
        if (str_contains($lower, 'خزنة') || str_contains($lower, 'رصيد') || str_contains($lower, 'كاش') || str_contains($lower, 'صندوق')) {
            return "المعلومات المالية التفصيلية (رصيد الخزنة) غير متاحة حاليًا في الـ Intelligence.\n\nممكن تفتح تقرير الخزنة مباشرة من القائمة الجانبية: 💰 التقارير ← الخزنة.\n\nأما إذا حابب، أقدر أساعدك في:
• إيراداتك والتحصيلات
• حجوزات الإيجار
• المخزون";
        }

        return "عذرًا، السؤال ده برّا نطاق المساعدة الحالي. 🙏\n\nأنا متخصص في شؤون الأتيليه:
• 📊 الإيرادات والتحصيلات
• 👗 حجوزات الإيجار
• 📦 المخزون والفساتين
• ⚠️ المرتجعات المتأخرة
• 📈 مقارنة الفترات
\nإزاي أقدر أساعدك في أي من دول؟";
    }

    // ═══════════════════════════════════════════════════════════════
    // DECISION SUPPORT ENGINES
    // ═══════════════════════════════════════════════════════════════

        private function decisionDiscount($user, string $tenantSlug): string
    {
        $resResult = $this->toolExecutor->tryAnswer($user, 'كيف وضع الشغل؟', $tenantSlug);
        $revenue = $resResult['response'] ?? 'لا توجد بيانات حديثة.';

        $response = "📊 قرار: عمل خصم على الإيجارات

";
        $response .= "══ الوضع الحالي ══
{$revenue}

";
        $response .= "══ الخيارات ══

";
        $response .= "1️⃣ خصم 10% مؤقت — يجذب عملاء جدد سريعًا. مخاطر: يقلل هامش الربح.

";
        $response .= "2️⃣ عرض إيجار يومين بسعر يوم — يزيد معدل التدوير. مخاطر: يستنزف المخزون.

";
        $response .= "3️⃣ ما تعملش خصم — تحافظ على القيمة. مخاطر: ممكن يفوتك فرصة.

";
        $response .= "══ التوصية ══

";
        $response .= "لو عندك فساتين راكدة ومش بتتأجر — خصم مؤقت 10-15% يفيد.
";
        $response .= "لو المخزون قليل والطلب عالي — ما تعملش خصم.

";
        $response .= "إيه وضع مخزونك تحديدًا؟ أقدر أفحصلك الفساتين الراكدة.";
        return $response;
    }

private function decisionHire($user, string $tenantSlug): string
    {
        return "📊 قرار: تعيين موظفين\n\n══ الوضع الحالي ══\nلو عندك بيانات أداء الموظفين الحاليين، أقدر أحلللك هل التحم زايد ولا لا.\n\n══ الخيارات ══\n
1️⃣ **وظّف موظف جديد** — لو الإيرادات زايدة والشغل كتير.

2️⃣ **استخدم freelancers مؤقت** — لفترات الذروة بس.

3️⃣ **حسّن كفاءة الفريق الحالي** — تدريب + تحفيز.

══ التوصية ══\n
أفتح تقرير أداء الموظفين الأول. لو كلهم مشغولين 80%+ من الوقت — فكّر في التعيين.

أقدر أساعدك في فحص إيراداتك الحالية وشوف هل الشغل يستحق موظف جديد ولا لأ.";
    }

    private function decisionInventory($user, string $tenantSlug): string
    {
        $invResult = $this->toolExecutor->tryAnswer($user, 'إيه الفساتين الراكدة؟', $tenantSlug);
        $inventory = $invResult['response'] ?? 'لا توجد بيانات مخزون.';

        return "📊 قرار: شراء فساتين جديدة\n\n══ الوضع الحالي ══\n{$inventory}\n\n══ الخيارات ══\n
1️⃣ **اشتري فساتين جديدة** — لو المخزون قليل أو في فئات ناقصة.

2️⃣ **صفي الفساتين الراكدة أولاً** — أعمل عرض على القديم قبل ما تجيب جديد.

3️⃣ **استأجر فساتين مؤقتًا** — جرّب الطلب قبل الشراء.

══ التوصية ══\n
لو فوق 30% من المخزون راكد → صفي القديم أولاً.\n
لو المخزون أقل من 20 فستان → جدّد.

لو كل حاجة بتتحرك → أضف تشكيلة جديدة لزيادة الطلب.";
    }

    private function decisionBranch($user, string $tenantSlug): string
    {
        return "📊 قرار: فتح فرع جديد\n\n══ الوضع الحالي ══\nفتح فرع جديد قرار كبير يحتاج:
• استقرار إيرادي (6 شهور+)
• إيرادات شهرية ثابتة
• فريق إداري قوي\n\n══ الخيارات ══\n
1️⃣ **افتح فرع جديد** — لو الإيرادات ثابتة والطلب زايد.

2️⃣ **وسّع الفرع الحالي** — أرخص وأقل مخاطرة.

3️⃣ **استخدم online booking** — وصل لعملاء جدد بدون فرع.

══ التوصية ══\n
إيراداتك الحالية كام الشهر؟ لو أقل من 100,000 جنيه شهريًا → ركّز على الفرع الحالي الأول.

أقدر أفحصلك إيراداتك وتأكد إنت جاهز ولا لأ.";
    }

    private function buildComparisonFromContext(string $content, array $ctx): string
    {
        return "محتاج أجيب البيانات لمقارنة الفترات.\n\nممكن تحدد:
• إيه اللي عايز تقارنه؟ (إيرادات/حجوزات/مصاريف)
• الفترة الحالية مقابل أي فترة؟";
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITIES
    // ═══════════════════════════════════════════════════════════════

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

    private function sanitizeResponse(string $response): string
    {
        // Remove think tags
        $response = preg_replace('/<think>.*?<\/think>/s', '', $response);
        // Remove tool call markers
        $response = preg_replace('/\[تم استدعاء.*$/m', '', $response);
        // Remove planning language
        $response = preg_replace('/سأقوم ب.*?\./s', '', $response);
        $response = preg_replace('/يرجى الانتظار.*?$/m', '', $response);
        // Remove function call syntax
        $response = preg_replace('/\b\w+\(.*\)/', '', $response);
        // Remove JSON-like blocks
        $response = preg_replace('/\{[^}]+\}/', '', $response);
        // Clean up
        $response = trim($response);
        // Remove empty lines
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        return trim($response);
    }
}
