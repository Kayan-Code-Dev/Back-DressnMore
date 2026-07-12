<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class ActiveReservationsTool implements SafeBusinessTool
{
    public function name(): string { return 'active_reservations'; }
    public function description(): string { return 'Active rental reservations with count, total value, and upcoming returns.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['invoices.view']; }
    public function supports(string $intent): bool { return $intent === 'active_reservations'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $activeStatuses = [InvoiceStatus::CONFIRMED->value, InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value];
        $now = now($context->timezone())->toDateString();
        $query = Invoice::query()->where('type', InvoiceType::RENT->value)->whereIn('status', $activeStatuses)->whereDate('return_date', '>=', $now);
        $context->applyBranchScope($query);

        $summary = (clone $query)->select([DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(total), 0) as total_value'), DB::raw("SUM(CASE WHEN DATE(return_date) = '{$now}' THEN 1 ELSE 0 END) as returning_today"), DB::raw("SUM(CASE WHEN DATE(return_date) = DATE_ADD('{$now}', INTERVAL 1 DAY) THEN 1 ELSE 0 END) as returning_tomorrow")])->first();

        $upcoming = (clone $query)->whereDate('return_date', '<=', now($context->timezone())->addDays(7)->toDateString())->orderBy('return_date')->limit(10)->with('customer:id,name')->get(['invoice_number', 'customer_id', 'return_date', 'total'])->map(fn ($i) => ['invoice' => $i->invoice_number, 'customer' => $i->customer?->name ?? '—', 'return_date' => $i->return_date?->toDateString() ?? '', 'amount' => (float) $i->total])->toArray();

        $count = (int) ($summary->cnt ?? 0);
        if ($count === 0) return BusinessToolResult::empty($this->name(), $this->version(), $this->scope($context));
        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['count' => $count, 'total_value' => round((float) ($summary->total_value ?? 0), 2), 'returning_today' => (int) ($summary->returning_today ?? 0), 'returning_tomorrow' => (int) ($summary->returning_tomorrow ?? 0), 'currency' => $context->currency(), 'upcoming_returns' => $upcoming], scope: $this->scope($context));
    }

    private function scope(BusinessToolContext $ctx): array { return ['tenant' => $ctx->tenantSlug(), 'branches' => $ctx->authorizedBranchIds() ?? 'all', 'as_of' => now($ctx->timezone())->toDateTimeString()]; }
}
