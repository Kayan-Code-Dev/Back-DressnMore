<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

use App\Models\Tenant\Intelligence\AiToolExecution;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;

class BusinessToolExecutor
{
    private BusinessToolRegistry $registry;
    private BusinessQuestionRouter $router;

    public function __construct()
    {
        $this->registry = BusinessToolRegistry::withStandardTools();
        $this->router = new BusinessQuestionRouter();
    }

    public function tryAnswer(User $user, string $message, string $tenantSlug): array
    {
        $start = microtime(true);
        $intents = $this->router->route($message);

        // No intent matched — not a business question
        if ($intents === []) {
            return [
                'handled' => false,
                'response' => null,
                'facts' => [],
                'tools_executed' => [],
                'execution_ms' => 0,
            ];
        }

        $context = BusinessToolContext::forUser($user, $tenantSlug);
        $results = [];
        $toolNames = [];

        foreach ($intents as $intent) {
            foreach ($this->router->toolsForIntent($intent) as $toolName) {
                $result = $this->registry->execute($toolName, $context);
                $results[] = $result;
                $toolNames[] = $toolName;
                $this->persistExecution($result, $toolName, $context);
            }
        }

        $ms = (int) ((microtime(true) - $start) * 1000);
        $lang = $this->detectLanguage($message);
        $isArabic = $lang === 'ar';

        // Build deterministic Arabic response — NEVER call the model
        $response = $this->buildDeterministicResponse($results, $isArabic);

        return [
            'handled' => true,
            'response' => $response,
            'facts' => array_map(fn ($r) => $r->jsonSerialize(), $results),
            'tools_executed' => $toolNames,
            'execution_ms' => $ms,
            'model_needed' => false,
        ];
    }

    /**
     * Build a deterministic response directly from tool results.
     * No model call. Guaranteed fast Arabic formatting.
     */
    private function buildDeterministicResponse(array $results, bool $isArabic): string
    {
        if (!$isArabic) {
            return $this->buildEnglishResponse($results);
        }

        $okResults = array_filter($results, fn ($r) => $r->isOk());
        $emptyResults = array_filter($results, fn ($r) => $r->isEmpty());

        // All empty
        if ($okResults === [] && $emptyResults !== []) {
            return 'لا توجد بيانات متاحة للفترة المطلوبة.';
        }

        // All denied
        if (collect($results)->every(fn ($r) => $r->isDenied())) {
            return 'ليس لديك صلاحية الوصول لهذه البيانات.';
        }

        // Single tool — direct formatting
        if (count($okResults) === 1) {
            return reset($okResults)->formatArabic();
        }

        // Multiple tools — composite snapshot
        return $this->buildCompositeArabic($okResults);
    }

    /**
     * Composite response for multi-tool questions like "كيف وضع الشغل؟"
     */
    private function buildCompositeArabic(array $results): string
    {
        $sections = [];

        foreach ($results as $result) {
            $sections[] = $result->formatArabic();
        }

        if ($sections === []) {
            return 'لا توجد بيانات كافية.';
        }

        return implode("\n\n", $sections);
    }

    private function buildEnglishResponse(array $results): string
    {
        $okResults = array_filter($results, fn ($r) => $r->isOk());
        if ($okResults === []) {
            return 'No data available for the requested period.';
        }
        if (count($okResults) === 1) {
            return reset($okResults)->toTrustedFactsBlock();
        }
        return implode("\n\n", array_map(fn ($r) => $r->toTrustedFactsBlock(), $okResults));
    }

    public function toolMetadata(): array
    {
        return $this->registry->metadata();
    }

    private function persistExecution(BusinessToolResult $result, string $toolName, BusinessToolContext $context): void
    {
        try {
            AiToolExecution::create([
                'tool_name' => $toolName,
                'tool_version' => $result->version,
                'status' => $result->status,
                'facts' => $result->facts,
                'scope' => array_merge($result->scope, [
                    'tenant' => $context->tenantSlug(),
                    'user_id' => $context->userId(),
                ]),
                'warnings' => $result->warnings,
                'error' => $result->error,
                'execution_ms' => $result->executionMs,
                'executed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist tool execution', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function detectLanguage(string $text): string
    {
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars = mb_strlen(preg_replace('/\s/', '', $text)) ?: 1;
        return ($arabicCount / $totalChars) > 0.3 ? 'ar' : 'en';
    }
}
