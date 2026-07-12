<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class ActiveCustomersTool implements SafeBusinessTool
{
    public function name(): string { return 'active_customers'; }
    public function description(): string { return 'Top customers by revenue and count, plus new customers in period.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['customers.view', 'reports.customers']; }
    public function supports(string $intent): bool { return $intent === 'active_customers'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $excluded = [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value];
        $topQuery = Invoice::query()->select(['customer_id', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(total), 0) as spent')])->whereNotIn('status', $excluded);
        $context->applyBranchScope($topQuery); $context->applyDateRange($topQuery);

        $topCustomers = (clone $topQuery)->groupBy('customer_id')->orderByDesc('spent')->limit(10)->with('customer:id,name')->get()->map(fn ($c) => ['name' => $c->customer?->name ?? '—', 'invoice_count' => (int) $c->cnt, 'total_spent' => round((float) $c->spent, 2)])->toArray();

        $newCount = Customer::query()->whereBetween('created_at', [$context->dateFrom()->toDateTimeString(), $context->dateTo()->toDateTimeString()]);
        $context->applyBranchScope($newCount);
        $newCustomers = $newCount->count();
        $activeCount = (clone $topQuery)->distinct('customer_id')->count('customer_id');
        $totalCount = Customer::query(); $context->applyBranchScope($totalCount); $totalCustomers = $totalCount->count();

        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['period' => $context->periodLabel(), 'currency' => $context->currency(), 'active_customers' => $activeCount, 'total_customer_base' => $totalCustomers, 'new_customers' => $newCustomers, 'top_customers' => $topCustomers], scope: ['tenant' => $context->tenantSlug(), 'branches' => $context->authorizedBranchIds() ?? 'all']);
    }
}
