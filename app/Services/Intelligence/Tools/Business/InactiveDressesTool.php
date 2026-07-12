<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\DressStatus;
use App\Models\Tenant\Dress;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class InactiveDressesTool implements SafeBusinessTool
{
    public function name(): string { return 'inactive_dresses'; }
    public function description(): string { return 'Dress inventory status: available, rented, maintenance, utilization rate.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['dresses.view', 'inventory.view']; }
    public function supports(string $intent): bool { return $intent === 'inactive_dresses'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $query = Dress::query(); $context->applyBranchScope($query);
        $byStatus = (clone $query)->select(['status', DB::raw('COUNT(*) as cnt')])->groupBy('status')->pluck('cnt', 'status')->toArray();
        $available = (int) ($byStatus[DressStatus::AVAILABLE->value] ?? 0);
        $rented = (int) ($byStatus[DressStatus::RENTED->value] ?? 0);
        $maintenance = (int) ($byStatus[DressStatus::MAINTENANCE->value] ?? 0);
        $sold = (int) ($byStatus[DressStatus::SOLD->value] ?? 0);
        $unavailable = (int) ($byStatus[DressStatus::UNAVAILABLE->value] ?? 0);
        $total = array_sum($byStatus);

        $slowMoverDate = now($context->timezone())->subDays(60)->toDateTimeString();
        $slowQuery = Dress::query()->where('status', DressStatus::AVAILABLE->value)->where(fn ($q) => $q->whereNull('updated_at')->orWhere('updated_at', '<', $slowMoverDate));
        $context->applyBranchScope($slowQuery);
        $slowCount = $slowQuery->count();

        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['total_dresses' => $total, 'available' => $available, 'rented' => $rented, 'maintenance' => $maintenance, 'sold' => $sold, 'unavailable' => $unavailable, 'utilization_rate' => $total > 0 ? round(($rented / $total) * 100, 1) : 0, 'slow_movers' => $slowCount, 'currency' => $context->currency()], scope: ['tenant' => $context->tenantSlug(), 'branches' => $context->authorizedBranchIds() ?? 'all', 'as_of' => now($context->timezone())->toDateTimeString()]);
    }
}
