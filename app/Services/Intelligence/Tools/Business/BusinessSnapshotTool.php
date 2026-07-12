<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Business;

use App\Enums\DressStatus;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Support\Facades\DB;

class BusinessSnapshotTool implements SafeBusinessTool
{
    public function name(): string { return 'business_snapshot'; }
    public function description(): string { return 'Concise dashboard overview combining revenue, rentals, inventory, and customers.'; }
    public function version(): string { return '1.0.0'; }
    public function requiredPermissions(): array { return ['dashboard.view']; }
    public function supports(string $intent): bool { return $intent === 'business_snapshot'; }

    public function execute(BusinessToolContext $context): BusinessToolResult
    {
        $now = now($context->timezone());
        $today = $now->toDateString();
        $excluded = [InvoiceStatus::CANCELLED->value, InvoiceStatus::DRAFT->value];
        $monthFrom = $now->clone()->startOfMonth(); $monthTo = $now->clone()->endOfMonth();

        $revQuery = Invoice::query()->whereNotIn('status', $excluded)->whereBetween('created_at', [$monthFrom->toDateTimeString(), $monthTo->toDateTimeString()]);
        $context->applyBranchScope($revQuery); $rev = $revQuery->select([DB::raw('COALESCE(SUM(total), 0) as total'), DB::raw('COUNT(*) as cnt')])->first();

        $todayRev = Invoice::query()->whereNotIn('status', $excluded)->whereDate('created_at', $today);
        $context->applyBranchScope($todayRev); $tRev = $todayRev->select([DB::raw('COALESCE(SUM(total), 0) as total'), DB::raw('COUNT(*) as cnt')])->first();

        $activeStatuses = [InvoiceStatus::CONFIRMED->value, InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value, InvoiceStatus::DELIVERED->value];
        $activeRentals = Invoice::query()->where('type', 'rent')->whereIn('status', $activeStatuses)->whereDate('return_date', '>=', $today);
        $context->applyBranchScope($activeRentals); $activeCount = $activeRentals->count();

        $overdue = Invoice::query()->where('type', 'rent')->whereNotIn('status', [InvoiceStatus::RETURNED->value, InvoiceStatus::CANCELLED->value])->whereDate('return_date', '<', $today);
        $context->applyBranchScope($overdue); $overdueCount = $overdue->count();

        $inv = Dress::query(); $context->applyBranchScope($inv);
        $invResult = $inv->select([DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available"), DB::raw("SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented"), DB::raw("SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance"), DB::raw('COUNT(*) as total')])->first();

        $totalCustomers = Customer::query(); $context->applyBranchScope($totalCustomers); $totalCust = $totalCustomers->count();
        $newCust = Customer::query()->whereBetween('created_at', [$monthFrom->toDateTimeString(), $monthTo->toDateTimeString()]);
        $context->applyBranchScope($newCust); $newCount = $newCust->count();

        return new BusinessToolResult(tool: $this->name(), version: $this->version(), status: 'ok', facts: ['period' => $monthFrom->translatedFormat('F Y'), 'currency' => $context->currency(), 'revenue' => ['month_total' => round((float) ($rev->total ?? 0), 2), 'month_invoices' => (int) ($rev->cnt ?? 0), 'today_total' => round((float) ($tRev->total ?? 0), 2), 'today_invoices' => (int) ($tRev->cnt ?? 0)], 'rentals' => ['active' => $activeCount, 'overdue' => $overdueCount], 'inventory' => ['total' => (int) ($invResult->total ?? 0), 'available' => (int) ($invResult->available ?? 0), 'rented' => (int) ($invResult->rented ?? 0), 'maintenance' => (int) ($invResult->maintenance ?? 0), 'utilization' => ((int) ($invResult->total ?? 0)) > 0 ? round(((int) ($invResult->rented ?? 0) / (int) $invResult->total) * 100, 1) : 0], 'customers' => ['total' => $totalCust, 'new_this_month' => $newCount]], scope: ['tenant' => $context->tenantSlug(), 'as_of' => $now->toDateTimeString()]);
    }
}
