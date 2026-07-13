<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

use JsonSerializable;

final class BusinessToolResult implements JsonSerializable
{
    public function __construct(
        public readonly string $tool,
        public readonly string $version,
        public readonly string $status,
        public readonly array $facts = [],
        public readonly array $scope = [],
        public readonly array $warnings = [],
        public readonly ?string $error = null,
        public readonly ?int $executionMs = null,
    ) {}

    public function isOk(): bool { return $this->status === 'ok'; }
    public function isEmpty(): bool { return $this->status === 'empty'; }
    public function isDenied(): bool { return $this->status === 'denied'; }
    public function isError(): bool { return $this->status === 'error'; }

    public function formatArabic(): string
    {
        return match ($this->tool) {
            'revenue_summary' => $this->formatRevenueArabic(),
            'active_reservations' => $this->formatReservationsArabic(),
            'late_returns' => $this->formatLateReturnsArabic(),
            'pending_deliveries' => $this->formatDeliveriesArabic(),
            'inactive_dresses' => $this->formatInactiveDressesArabic(),
            'business_snapshot' => $this->formatSnapshotArabic(),
            'business_health' => $this->formatHealthArabic(),
            'daily_brief' => $this->formatDailyBriefArabic(),
            default => $this->formatGenericArabic(),
        };
    }

    private function formatRevenueArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد إيرادات مسجلة خلال الفترة المطلوبة.';
        }
        $f = $this->facts;
        $period = $f['period'] ?? 'الفترة الحالية';
        $total = number_format($f['total_revenue'] ?? 0);
        $count = $f['invoice_count'] ?? 0;
        $byType = $f['by_type'] ?? [];
        $lines = ["إيرادات {$period}:"];
        $lines[] = "إجمالي الإيرادات: {$total} جنيه مصري";
        $lines[] = "عدد الفواتير: {$count}";
        $typeLabels = ['rent' => 'إيجار', 'sell' => 'بيع', 'tailoring' => 'تفصيل'];
        foreach ($byType as $type => $amount) {
            if ($amount > 0) {
                $label = $typeLabels[$type] ?? $type;
                $lines[] = "{$label}: " . number_format($amount) . " جنيه";
            }
        }
        return implode("\n", $lines);
    }

    private function formatReservationsArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد حجوزات نشطة حالياً.';
        }
        $f = $this->facts;
        $active = $f['active_count'] ?? 0;
        $upcoming = $f['upcoming_pickups'] ?? 0;
        $lines = ["الحجوزات النشطة: {$active}"];
        if ($upcoming > 0) {
            $lines[] = "استلامات قادمة: {$upcoming}";
        }
        return implode("\n", $lines);
    }

    private function formatLateReturnsArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد مرتجعات متأخرة. كل الفساتين المؤجرة في موعدها.';
        }
        $f = $this->facts;
        $count = $f['overdue_count'] ?? 0;
        $items = $f['overdue_items'] ?? [];
        if ($count === 0) {
            return 'لا توجد مرتجعات متأخرة. كل الفساتين المؤجرة في موعدها.';
        }
        $lines = ["تنبيه: {$count} مرتجع متأخر"];
        foreach (array_slice($items, 0, 5) as $item) {
            $customer = $item['customer'] ?? 'عميل';
            $dress = $item['dress'] ?? 'فستان';
            $days = $item['days_overdue'] ?? 0;
            $lines[] = "- {$customer}: {$dress} (متأخر {$days} يوم)";
        }
        if ($count > 5) {
            $lines[] = "و " . ($count - 5) . " إضافية...";
        }
        return implode("\n", $lines);
    }

    private function formatDeliveriesArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد تسليمات معلقة اليوم.';
        }
        $f = $this->facts;
        $pending = $f['pending_count'] ?? 0;
        $today = $f['today_count'] ?? 0;
        $lines = ["التسليمات:"];
        $lines[] = "معلقة: {$pending}";
        $lines[] = "موعدها اليوم: {$today}";
        return implode("\n", $lines);
    }

    private function formatInactiveDressesArabic(): string
    {
        if ($this->isEmpty()) {
            return 'كل الفساتين في حالة جيدة ولا يوجد راكد.';
        }
        $f = $this->facts;
        $count = $f['inactive_count'] ?? 0;
        $items = $f['inactive_items'] ?? [];
        if ($count === 0) {
            return 'كل الفساتين في حالة جيدة ولا يوجد راكد.';
        }
        $lines = ["{$count} فستان راكد (لم يُحجز منذ فترة):"];
        foreach (array_slice($items, 0, 5) as $item) {
            $name = $item['name'] ?? 'فستان';
            $days = $item['days_inactive'] ?? 0;
            $lines[] = "- {$name} ({$days} يوم)";
        }
        if ($count > 5) {
            $lines[] = "و " . ($count - 5) . " إضافية...";
        }
        return implode("\n", $lines);
    }

    private function formatSnapshotArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد بيانات كافية لإنشاء لقطة.';
        }
        $f = $this->facts;
        $period = $f['period'] ?? '';
        $rev = $f['revenue'] ?? [];
        $rentals = $f['rentals'] ?? [];
        $inventory = $f['inventory'] ?? [];
        $customers = $f['customers'] ?? [];
        $lines = ["=== ملخص الأتيليه - {$period} ==="];
        $monthTotal = number_format($rev['month_total'] ?? 0);
        $monthInv = $rev['month_invoices'] ?? 0;
        $todayTotal = number_format($rev['today_total'] ?? 0);
        $lines[] = "الإيرادات: {$monthTotal} جنيه ({$monthInv} فاتورة) - اليوم: {$todayTotal} جنيه";
        $activeRentals = $rentals['active'] ?? 0;
        $overdueRentals = $rentals['overdue'] ?? 0;
        $lines[] = "الحجوزات: {$activeRentals} نشطة";
        if ($overdueRentals > 0) {
            $lines[] = "مرتجعات متأخرة: {$overdueRentals}";
        }
        $totalInv = $inventory['total'] ?? 0;
        $availInv = $inventory['available'] ?? 0;
        $util = $inventory['utilization'] ?? 0;
        $lines[] = "المخزون: {$totalInv} فستان ({$availInv} متاح) - الاستغلال: {$util}%";
        $totalCust = $customers['total'] ?? 0;
        $newCust = $customers['new_this_month'] ?? 0;
        $lines[] = "العملاء: {$totalCust} - جدد هذا الشهر: {$newCust}";
        return implode("\n", $lines);
    }

    private function formatHealthArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد بيانات كافية لتقييم صحة النشاط.';
        }
        $f = $this->facts;
        $score = $f['health_score'] ?? null;
        $label = $f['health_label'] ?? '';
        $strength = $f['main_strength'] ?? '';
        $risk = $f['main_risk'] ?? '';
        if ($score === null) {
            return 'لا توجد بيانات كافية لتقييم صحة النشاط.';
        }
        $lines = ["صحة النشاط: {$label} ({$score}/100)"];
        if ($strength) {
            $lines[] = "{$strength}";
        }
        if ($risk) {
            $lines[] = "تنبيه: {$risk}";
        }
        return implode("\n", $lines);
    }

    private function formatDailyBriefArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد بيانات كافية لملخص اليوم.';
        }
        $f = $this->facts;
        $date = $f['date'] ?? 'اليوم';
        $rev = $f['revenue'] ?? [];
        $activity = $f['activity'] ?? [];
        $alerts = $f['alerts'] ?? [];
        $lines = ["=== موجز اليوم - {$date} ==="];
        $todayRev = number_format($rev['today'] ?? 0);
        $lines[] = "إيراد اليوم: {$todayRev} جنيه";
        $newInv = $activity['new_invoices'] ?? 0;
        $newCust = $activity['new_customers'] ?? 0;
        $overdueRet = $activity['overdue_rentals'] ?? 0;
        $lines[] = "فواتير جديدة: {$newInv} | عملاء جدد: {$newCust}";
        if ($overdueRet > 0) {
            $lines[] = "مرتجعات متأخرة: {$overdueRet}";
        }
        $lowStock = $alerts['low_stock_categories'] ?? 0;
        if ($lowStock > 0) {
            $lines[] = "مخزون منخفض: {$lowStock} فئات";
        }
        return implode("\n", $lines);
    }

    private function formatGenericArabic(): string
    {
        if ($this->isEmpty()) {
            return 'لا توجد بيانات متاحة.';
        }
        $lines = [];
        foreach ($this->flattenFacts() as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }
        return implode("\n", $lines);
    }

    public function toTrustedFactsBlock(): string
    {
        $lines = ["=== {$this->tool} (v{$this->version}) ==="];
        foreach ($this->flattenFacts() as $key => $value) { $lines[] = "- {$key}: {$value}"; }
        if ($this->warnings !== []) { $lines[] = ''; $lines[] = 'Warnings:'; foreach ($this->warnings as $w) { $lines[] = "- {$w}"; } }
        return implode("\n", $lines);
    }

    private function flattenFacts(): array { $flat = []; $this->flatten('', $this->facts, $flat); return $flat; }

    private function flatten(string $prefix, mixed $value, array &$out): void
    {
        if (is_array($value) && !array_is_list($value)) { foreach ($value as $k => $v) { $this->flatten($prefix ? "{$prefix}.{$k}" : $k, $v, $out); } }
        elseif (is_array($value)) { foreach ($value as $i => $v) { $this->flatten($prefix ? "{$prefix}.[{$i}]" : "[{$i}]", $v, $out); } }
        else { $out[$prefix] = is_string($value) ? $value : json_encode($value); }
    }

    public static function denied(string $tool, array $missing): self
    {
        return new self(tool: $tool, version: '0.0.0', status: 'denied', facts: ['access' => 'denied'], warnings: ['Missing permissions: ' . implode(', ', $missing)]);
    }

    public static function error(string $tool, string $message, array $scope = []): self
    {
        return new self(tool: $tool, version: '0.0.0', status: 'error', facts: ['error' => $message], scope: $scope, error: $message);
    }

    public static function empty(string $tool, string $version, array $scope = []): self
    {
        return new self(tool: $tool, version: $version, status: 'empty', facts: ['result' => 'no data available for the requested period'], scope: $scope);
    }

    public function jsonSerialize(): array
    {
        return ['tool' => $this->tool, 'version' => $this->version, 'status' => $this->status, 'facts' => $this->facts, 'scope' => $this->scope, 'warnings' => $this->warnings, 'error' => $this->error, 'execution_ms' => $this->executionMs];
    }
}
