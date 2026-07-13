<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\Tenant\Intelligence\AiConversation;

/**
 * Structured conversation memory for multi-turn context.
 */
final class ConversationMemory
{
    public string $currentTopic = '';
    public string $currentPeriod = 'this_month';
    public string $comparisonPeriod = 'last_month';
    public string $branchScope = 'all';
    public array $lastToolsUsed = [];
    public array $importantFacts = [];
    public ?string $unresolvedClarification = null;
    public string $conversationSummary = '';

    public function __construct(AiConversation $conversation)
    {
        $this->loadFromConversation($conversation);
    }

    private function loadFromConversation(AiConversation $conversation): void
    {
        $meta = $conversation->metadata ?? [];
        $this->currentTopic = $meta['current_topic'] ?? '';
        $this->currentPeriod = $meta['current_period'] ?? 'this_month';
        $this->comparisonPeriod = $meta['comparison_period'] ?? 'last_month';
        $this->branchScope = $meta['branch_scope'] ?? 'all';
        $this->lastToolsUsed = $meta['last_tools_used'] ?? [];
        $this->importantFacts = $meta['important_facts'] ?? [];
        $this->unresolvedClarification = $meta['unresolved_clarification'] ?? null;
        $this->conversationSummary = $meta['conversation_summary'] ?? '';
    }

    public function persist(AiConversation $conversation): void
    {
        $conversation->update(['metadata' => [
            'current_topic' => $this->currentTopic,
            'current_period' => $this->currentPeriod,
            'comparison_period' => $this->comparisonPeriod,
            'branch_scope' => $this->branchScope,
            'last_tools_used' => $this->lastToolsUsed,
            'important_facts' => $this->importantFacts,
            'unresolved_clarification' => $this->unresolvedClarification,
            'conversation_summary' => $this->conversationSummary,
        ]]);
    }

    /**
     * Build a compact context message for the model.
     */
    public function toContextMessage(): array
    {
        $lines = ['[سياق المحادثة الحالي]'];
        if ($this->currentTopic) {
            $lines[] = "الموضوع: {$this->currentTopic}";
        }
        if ($this->currentPeriod) {
            $lines[] = "الفترة: {$this->currentPeriod}";
        }
        if ($this->comparisonPeriod) {
            $lines[] = "فترة المقارنة: {$this->comparisonPeriod}";
        }
        if ($this->lastToolsUsed) {
            $lines[] = "أدوات مستخدمة: " . implode(', ', $this->lastToolsUsed);
        }
        if ($this->importantFacts) {
            $lines[] = "بيانات مهمة: " . json_encode($this->importantFacts, JSON_UNESCAPED_UNICODE);
        }
        if ($this->conversationSummary) {
            $lines[] = "ملخص: {$this->conversationSummary}";
        }

        return [
            'role' => 'system',
            'content' => implode("\n", $lines),
        ];
    }

    public function updateTopic(string $topic): void
    {
        $this->currentTopic = $topic;
    }

    public function updatePeriod(string $period): void
    {
        $this->currentPeriod = $period;
    }

    public function recordToolUse(string $toolName): void
    {
        if (!in_array($toolName, $this->lastToolsUsed)) {
            $this->lastToolsUsed[] = $toolName;
        }
        if (count($this->lastToolsUsed) > 10) {
            $this->lastToolsUsed = array_slice($this->lastToolsUsed, -10);
        }
    }

    public function recordFacts(array $facts): void
    {
        $this->importantFacts = array_merge($this->importantFacts, $facts);
        if (count($this->importantFacts) > 20) {
            $this->importantFacts = array_slice($this->importantFacts, -20);
        }
    }
}
