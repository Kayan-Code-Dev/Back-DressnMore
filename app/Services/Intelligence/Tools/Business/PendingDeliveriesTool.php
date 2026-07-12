<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class PendingDeliveriesTool implements SafeBusinessTool
{
    public function name(): string { return 'pending_deliveries'; }
    public function description(): string { return 'Invoices pending delivery or return delivery.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['invoice_delivery.view']; }
    public function supports(string $intent): bool { return $intent === 'pending_deliveries'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $now = now($context->timezone())->toDateString();
        $deliveryQuery = Invoice::query()->whereNotIn('status', [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value])->whereNotNull('delivery_date')->whereNull('delivered_at')->whereDate('delivery_date', '>=', $now);
        $context->applyBranchScope($deliveryQuery);

        $summary = (clone $deliveryQuery)->select([DB::raw('COUNT(*) as cnt'), DB::raw("SUM(CASE WHEN DATE(delivery_date) = '{$now}' THEN 1 ELSE 0 END) as today"), DB::raw("SUM(CASE WHEN DATE(delivery_date) = DATE_ADD('{$now}', INTERVAL 1 DAY) THEN 1 ELSE 0 END) as tomorrow"), DB::raw("SUM(CASE WHEN DATE(delivery_date) < '{$now}' THEN 1 ELSE 0 END) as overdue")])->first();

        $pending = (clone $deliveryQuery)->orderBy('delivery_date')->limit(15)->with('customer:id,name,phone')->get(['invoice_number', 'customer_id', 'delivery_date', 'total'])->map(fn ($i) => ['invoice' => $i->invoice_number, 'customer' => $i->customer?->name ?? '—', 'phone' => $i->customer?->phone ?? '', 'scheduled_date' => $i->delivery_date?->toDateString() ?? '', 'amount' => (float) $i->total])->toArray();

        $count = (int) ($summary->cnt ?? 0);
        if ($count === 0) return BusinessToolResult::empty($this->name(), $this->version(), $this->scope($context));
        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['pending_count' => $count, 'scheduled_today' => (int) ($summary->today ?? 0), 'scheduled_tomorrow' => (int) ($summary->tomorrow ?? 0), 'overdue' => (int) ($summary->overdue ?? 0), 'pending_items' => $pending], scope: $this->scope($context));
    }

    private function scope(BusinessToolContext $ctx): array { return ['tenant' => $ctx->tenantSlug(), 'branches' => $ctx->authorizedBranchIds() ?? 'all', 'as_of' => now($ctx->timezone())->toDateTimeString()]; }
}
