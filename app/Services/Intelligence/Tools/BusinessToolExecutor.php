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
        if ($intents === []) { return ['handled' => false, 'response' => null, 'facts' => [], 'tools_executed' => [], 'execution_ms' => 0]; }

        $context = BusinessToolContext::forUser($user, $tenantSlug);
        $results = []; $toolNames = [];
        foreach ($intents as $intent) {
            foreach ($this->router->toolsForIntent($intent) as $toolName) {
                $result = $this->registry->execute($toolName, $context);
                $results[] = $result; $toolNames[] = $toolName;
                $this->persistExecution($result, $toolName, $context);
            }
        }
        $ms = (int) ((microtime(true) - $start) * 1000);

        $allOk = collect($results)->every(fn ($r) => $r->isOk() || $r->isEmpty());
        if ($allOk && count($results) === 1) {
            $fast = (new TrustedFactsPromptBuilder())->formatFast($results, $this->detectLanguage($message));
            if ($fast !== null && mb_strlen($fast) < 500) { return ['handled' => true, 'response' => $fast, 'facts' => array_map(fn ($r) => $r->jsonSerialize(), $results), 'tools_executed' => $toolNames, 'execution_ms' => $ms, 'model_needed' => false]; }
        }

        $okResults = array_filter($results, fn ($r) => $r->isOk());
        if ($okResults === []) {
            return ['handled' => true, 'response' => $this->detectLanguage($message) === 'ar' ? 'لا توجد بيانات متاحة للفترة المطلوبة.' : 'No data available for the requested period.', 'facts' => array_map(fn ($r) => $r->jsonSerialize(), $results), 'tools_executed' => $toolNames, 'execution_ms' => $ms, 'model_needed' => false];
        }

        $lang = $this->detectLanguage($message);
        $promptBuilder = new TrustedFactsPromptBuilder();
        $factsPrompt = $promptBuilder->build($okResults, $message, $lang);
        return ['handled' => true, 'response' => null, 'facts_prompt' => $factsPrompt, 'facts' => array_map(fn ($r) => $r->jsonSerialize(), $results), 'tools_executed' => $toolNames, 'execution_ms' => $ms, 'model_needed' => true];
    }

    public function toolMetadata(): array { return $this->registry->metadata(); }

    private function persistExecution(BusinessToolResult $result, string $toolName, BusinessToolContext $context): void
    {
        try {
            AiToolExecution::create(['tool_name' => $toolName, 'tool_version' => $result->version, 'status' => $result->status, 'facts' => $result->facts, 'scope' => array_merge($result->scope, ['tenant' => $context->tenantSlug(), 'user_id' => $context->userId()]), 'warnings' => $result->warnings, 'error' => $result->error, 'execution_ms' => $result->executionMs, 'executed_at' => now()]);
        } catch (\Throwable $e) { Log::warning('Failed to persist tool execution', ['tool' => $toolName, 'error' => $e->getMessage()]); }
    }

    private function detectLanguage(string $text): string
    {
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars = mb_strlen(preg_replace('/\s/', '', $text)) ?: 1;
        return ($arabicCount / $totalChars) > 0.3 ? 'ar' : 'en';
    }
}
