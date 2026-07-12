<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class DailyBriefTool implements SafeBusinessTool
{
    public function name(): string { return 'daily_brief'; }
    public function description(): string { return 'Morning briefing: revenue, returns due, new customers, overdue alerts.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['dashboard.view']; }
    public function supports(string $intent): bool { return $intent === 'daily_brief'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $now = now($context->timezone());
        $today = $now->toDateString(); $yesterday = $now->clone()->subDay()->toDateString();
        $excluded = [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value];

        $todayRev = $this->dayRevenue($context, $excluded, $today);
        $yestRev = $this->dayRevenue($context, $excluded, $yesterday);

        $newInvoices = Invoice::query()->whereDate('created_at', $today); $context->applyBranchScope($newInvoices); $newInvoiceCount = $newInvoices->count();

        $activeStatuses = [InvoiceStatus::CONFIRMED->value, InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value];
        $returnsDue = Invoice::query()->where('type', 'rent')->whereIn('status', $activeStatuses)->whereDate('return_date', $today);
        $context->applyBranchScope($returnsDue); $returnsDueCount = $returnsDue->count();

        $newCustomers = Customer::query()->whereDate('created_at', $today); $context->applyBranchScope($newCustomers); $newCustomerCount = $newCustomers->count();

        $overdue = Invoice::query()->where('type', 'rent')->whereNotIn('status', [InvoiceStatus::RETURNED->value, InvoiceStatus::CANCELLED->value])->whereDate('return_date', '<', $today);
        $context->applyBranchScope($overdue); $overdueCount = $overdue->count();

        $lowStock = Dress::query()->where('status', 'available')->select([DB::raw('COUNT(*) as cnt')])->groupBy('dress_category_id')->having('cnt', '<', 5);
        $context->applyBranchScope($lowStock); $lowStockCategories = $lowStock->get()->count();

        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['date' => $now->translatedFormat('l, j F Y'), 'currency' => $context->currency(), 'revenue' => ['today' => round($todayRev['total'], 2), 'today_invoices' => $todayRev['count'], 'yesterday' => round($yestRev['total'], 2), 'change_percent' => $this->pctChange($yestRev['total'], $todayRev['total'])], 'activity' => ['new_invoices' => $newInvoiceCount, 'returns_due_today' => $returnsDueCount, 'new_customers' => $newCustomerCount, 'overdue_rentals' => $overdueCount], 'alerts' => ['low_stock_categories' => $lowStockCategories]], scope: ['tenant' => $context->tenantSlug(), 'date' => $today]);
    }

    private function dayRevenue(BusinessToolContext $ctx, array $statuses, string $date): array
    {
        $q = Invoice::query()->whereIn('status', $statuses)->whereDate('created_at', $date);
        $ctx->applyBranchScope($q);
        $r = $q->select([DB::raw('COALESCE(SUM(total), 0) as total'), DB::raw('COUNT(*) as cnt')])->first();
        return ['total' => (float) $r->total, 'count' => (int) $r->cnt];
    }

    private function pctChange(float $prev, float $curr): ?float { if ($prev <= 0) return $curr > 0 ? 100.0 : 0.0; return round((($curr - $prev) / $prev) * 100, 1); }
}
