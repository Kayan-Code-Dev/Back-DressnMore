<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class RevenueSummaryTool implements SafeBusinessTool
{
    public function name(): string { return 'revenue_summary'; }
    public function description(): string { return 'Total revenue by invoice type (rent, sell, tailoring) for the given period.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['invoices.view', 'reports.sales']; }
    public function supports(string $intent): bool { return $intent === 'revenue_summary'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $excludedStatuses = [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value];
        $current = $this->fetchRevenue($context, $excludedStatuses);
        $days = $context->dateFrom()->diffInDays($context->dateTo()) ?: 30;
        $prevFrom = $context->dateFrom()->clone()->subDays($days);
        $prevTo = $context->dateFrom()->clone()->subSecond();
        $previous = $this->fetchRevenue($context->withDateRange($prevFrom, $prevTo), $excludedStatuses);

        $facts = ['period' => $context->periodLabel(), 'currency' => $context->currency(), 'total_revenue' => round($current['total'], 2), 'invoice_count' => $current['count'], 'by_type' => ['rent' => round($current['rent'], 2), 'sell' => round($current['sell'], 2), 'tailoring' => round($current['tailoring'], 2)], 'previous_period_revenue' => round($previous['total'], 2), 'previous_period_count' => $previous['count'], 'change_percent' => $this->safePercentChange($previous['total'], $current['total'])];

        if ($current['count'] === 0) return BusinessToolResult::empty($this->name(), $this->version(), $this->scope($context));
        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: $facts, scope: $this->scope($context));
    }

    private function fetchRevenue(BusinessToolContext $ctx, array $excluded): array
    {
        $query = Invoice::query()->whereNotIn('status', $excluded);
        $ctx->applyBranchScope($query); $ctx->applyDateRange($query);
        $result = $query->select([DB::raw('COALESCE(SUM(total), 0) as total'), DB::raw('COUNT(*) as cnt'), DB::raw("COALESCE(SUM(CASE WHEN type = 'rent' THEN total ELSE 0 END), 0) as rent"), DB::raw("COALESCE(SUM(CASE WHEN type = 'sell' THEN total ELSE 0 END), 0) as sell"), DB::raw("COALESCE(SUM(CASE WHEN type = 'tailoring' THEN total ELSE 0 END), 0) as tailoring")])->first();
        return ['total' => (float) $result->total, 'count' => (int) $result->cnt, 'rent' => (float) $result->rent, 'sell' => (float) $result->sell, 'tailoring' => (float) $result->tailoring];
    }

    private function safePercentChange(float $prev, float $curr): ?float { if ($prev <= 0) return $curr > 0 ? 100.0 : 0.0; return round((($curr - $prev) / $prev) * 100, 1); }
    private function scope(BusinessToolContext $ctx): array { return ['tenant' => $ctx->tenantSlug(), 'branches' => $ctx->authorizedBranchIds() ?? 'all', 'date_from' => $ctx->dateFrom()->toDateTimeString(), 'date_to' => $ctx->dateTo()->toDateTimeString()]; }
}
