<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\RentalReturnSettlement;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class LateReturnsTool implements SafeBusinessTool
{
    public function name(): string { return 'late_returns'; }
    public function description(): string { return 'Overdue rental returns with days late and late fees.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['invoices.view', 'reports.rental']; }
    public function supports(string $intent): bool { return $intent === 'late_returns'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $now = now($context->timezone())->toDateString();
        $excluded = [InvoiceStatus::RETURNED->value, InvoiceStatus::CANCELLED->value];
        $query = Invoice::query()->where('type', InvoiceType::RENT->value)->whereNotIn('status', $excluded)->whereDate('return_date', '<', $now);
        $context->applyBranchScope($query);

        $summary = (clone $query)->select([DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(total), 0) as total_value'), DB::raw("AVG(DATEDIFF('{$now}', return_date)) as avg_days"), DB::raw("MAX(DATEDIFF('{$now}', return_date)) as max_days"), DB::raw("SUM(CASE WHEN DATEDIFF('{$now}', return_date) > 7 THEN 1 ELSE 0 END) as severe")])->first();

        $overdue = (clone $query)->orderBy('return_date')->limit(15)->with('customer:id,name')->get(['invoice_number', 'customer_id', 'return_date', 'total'])->map(fn ($i) => ['invoice' => $i->invoice_number, 'customer' => $i->customer?->name ?? '—', 'return_date' => $i->return_date?->toDateString() ?? '', 'days_late' => $i->return_date ? now($context->timezone())->diffInDays($i->return_date) : 0, 'amount' => (float) $i->total])->toArray();

        $feesQuery = RentalReturnSettlement::query()->where('late_days', '>', 0);
        $context->applyDateRange($feesQuery);
        $feesSummary = $feesQuery->select([DB::raw('COALESCE(SUM(late_fee), 0) as total_fees'), DB::raw('SUM(late_days) as total_days'), DB::raw('COUNT(*) as settlements')])->first();

        $count = (int) ($summary->cnt ?? 0);
        if ($count === 0) return BusinessToolResult::empty($this->name(), $this->version(), $this->scope($context));
        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['overdue_count' => $count, 'total_value_at_risk' => round((float) ($summary->total_value ?? 0), 2), 'avg_days_late' => round((float) ($summary->avg_days ?? 0), 1), 'max_days_late' => (int) ($summary->max_days ?? 0), 'severely_late' => (int) ($summary->severe ?? 0), 'currency' => $context->currency(), 'late_fees_collected' => round((float) ($feesSummary->total_fees ?? 0), 2), 'overdue_items' => $overdue], scope: $this->scope($context));
    }

    private function scope(BusinessToolContext $ctx): array { return ['tenant' => $ctx->tenantSlug(), 'branches' => $ctx->authorizedBranchIds() ?? 'all', 'as_of' => now($ctx->timezone())->toDateTimeString()]; }
}
