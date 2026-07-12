<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Contracts;

use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;

/**
 * Formal contract for every safe, read-only business intelligence tool.
 *
 * The AI Orchestrator NEVER gives the model database access.
 * Instead, Laravel controls the tool, executes queries deterministically,
 * and only passes structured facts to the 0.5B model for natural-language summarization.
 */
interface SafeBusinessTool
{
    public function name(): string;
    public function description(): string;
    public function version(): string;
    public function requiredPermissions(): array;
    public function supports(string $intent): bool;
    public function execute(BusinessToolContext $context): BusinessToolResult;
}
