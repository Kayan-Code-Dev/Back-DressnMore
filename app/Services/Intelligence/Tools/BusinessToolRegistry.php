<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

use App\Services\Intelligence\Tools\Business\ActiveCustomersTool;
use App\Services\Intelligence\Tools\Business\ActiveReservationsTool;
use App\Services\Intelligence\Tools\Business\BusinessHealthTool;
use App\Services\Intelligence\Tools\Business\BusinessSnapshotTool;
use App\Services\Intelligence\Tools\Business\DailyBriefTool;
use App\Services\Intelligence\Tools\Business\InactiveDressesTool;
use App\Services\Intelligence\Tools\Business\LateReturnsTool;
use App\Services\Intelligence\Tools\Business\PendingDeliveriesTool;
use App\Services\Intelligence\Tools\Business\RevenueSummaryTool;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Collection;

final class BusinessToolRegistry
{
    private Collection $tools;

    public function __construct() { $this->tools = collect(); }

    public function register(SafeBusinessTool $tool): self { $this->tools->put($tool->name(), $tool); return $this; }
    public function get(string $name): ?SafeBusinessTool { return $this->tools->get($name); }
    public function has(string $name): bool { return $this->tools->has($name); }
    public function all(): Collection { return $this->tools; }
    public function names(): array { return $this->tools->keys()->toArray(); }

    public function forIntent(string $intent): Collection
    {
        return $this->tools->filter(fn (SafeBusinessTool $t) => $t->supports($intent));
    }

    public function execute(string $name, BusinessToolContext $context): BusinessToolResult
    {
        $tool = $this->get($name);
        if ($tool === null) { return BusinessToolResult::error(tool: $name, message: "Tool '{$name}' is not registered."); }
        $required = $tool->requiredPermissions();
        if (! $context->hasAllPermissions($required)) { return BusinessToolResult::denied(tool: $name, missing: array_diff($required, $context->permissions())); }
        $start = microtime(true);
        try {
            $result = $tool->execute($context);
            $ms = (int) ((microtime(true) - $start) * 1000);
            return new BusinessToolResult(tool: $result->tool, version: $result->version, status: $result->status, facts: $result->facts, scope: array_merge($result->scope, ['execution_ms' => $ms]), warnings: $result->warnings, error: $result->error, executionMs: $ms);
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $start) * 1000);
            return BusinessToolResult::error(tool: $name, message: $e->getMessage(), scope: ['tenant' => $context->tenantSlug(), 'user_id' => $context->userId(), 'branch_id' => $context->branchId(), 'execution_ms' => $ms]);
        }
    }

    public function metadata(): array
    {
        return $this->tools->map(fn (SafeBusinessTool $t) => ['name' => $t->name(), 'description' => $t->description(), 'version' => $t->version(), 'permissions' => $t->requiredPermissions()])->values()->all();
    }

    public static function withStandardTools(): self
    {
        $registry = new self();
        $registry->register(new RevenueSummaryTool())->register(new ActiveReservationsTool())->register(new LateReturnsTool())->register(new PendingDeliveriesTool())->register(new ActiveCustomersTool())->register(new InactiveDressesTool())->register(new BusinessSnapshotTool())->register(new BusinessHealthTool())->register(new DailyBriefTool());
        return $registry;
    }
}
