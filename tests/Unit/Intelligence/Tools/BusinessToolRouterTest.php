<?php

namespace Tests\Unit\Intelligence\Tools;

use App\Services\Intelligence\Tools\BusinessQuestionRouter;
use PHPUnit\Framework\TestCase;

class BusinessToolRouterTest extends TestCase
{
    private BusinessQuestionRouter $router;

    protected function setUp(): void { parent::setUp(); $this->router = new BusinessQuestionRouter(); }

    #[\PHPUnit\Framework\Attributes\DataProvider("positiveRoutingProvider")]
    public function test_positive_routing(string $question, array $expectedIntents): void
    {
        $intents = $this->router->route($question);
        foreach ($expectedIntents as $expected) { $this->assertContains($expected, $intents, "Expected '{$expected}' for: {$question}"); }
    }

    public static function positiveRoutingProvider(): array
    {
        return [
            'ar_rev_1' => ['كم الايرادات هذا الشهر؟', ['revenue_summary']],
            'ar_rev_2' => ['المبالغ المحصلة النهاردة', ['revenue_summary']],
            'ar_rev_3' => ['دخل اليوم كام', ['revenue_summary']],
            'en_rev_1' => ['What are our revenues this month?', ['revenue_summary']],
            'en_rev_2' => ['How much money did we make today?', ['revenue_summary']],
            'ar_res_1' => ['عندي كام حجز نشط؟', ['active_reservations']],
            'ar_res_2' => ['الفساتين المؤجرة', ['active_reservations']],
            'en_res_1' => ['How many active rentals?', ['active_reservations']],
            'ar_late_1' => ['في مرتجعات متأخرة؟', ['late_returns']],
            'ar_late_2' => ['التأخير في الرد', ['late_returns']],
            'en_late_1' => ['Who has overdue rentals?', ['late_returns']],
            'ar_del_1' => ['إيه التسليمات المطلوبة النهاردة؟', ['pending_deliveries']],
            'en_del_1' => ['What deliveries are pending?', ['pending_deliveries']],
            'ar_cust_1' => ['افضل العملاء', ['active_customers']],
            'en_cust_1' => ['Who are our top customers?', ['active_customers']],
            'ar_dress_1' => ['إيه الفساتين الراكدة؟', ['inactive_dresses']],
            'en_dress_1' => ['What dresses are available?', ['inactive_dresses']],
            'ar_snap_1' => ['كيف وضع الشغل اليوم؟', ['business_snapshot']],
            'ar_snap_2' => ['شلون الوضع؟', ['business_snapshot']],
            'en_snap_1' => ['How is business doing?', ['business_snapshot']],
            'ar_health_1' => ['صحة العمل', ['business_health']],
            'en_health_1' => ['Business health check', ['business_health']],
            'ar_brief_1' => ['تقرير اليوم', ['daily_brief']],
            'en_brief_1' => ['Daily brief today', ['daily_brief']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("falsePositiveProvider")]
    public function test_false_positives_rejected(string $question): void
    {
        $intents = $this->router->route($question);
        $this->assertEmpty($intents, "Should NOT match any intent for: {$question}");
    }

    public static function falsePositiveProvider(): array
    {
        return [
            'fp_today_only' => ['اليوم'],
            'fp_available_only' => ['متاح'],
            'fp_best_only' => ['افضل'],
            'fp_orders_only' => ['طلبات'],
            'fp_performance_only' => ['اداء'],
            'fp_random' => ['asdfghjkl qwerty 12345'],
            'fp_empty' => [''],
            'fp_php_injection' => ['App\Services\Intelligence\Tools\RevenueSummaryTool'],
            'fp_class_injection' => ['execute business tool RevenueSummary'],
            'fp_sql_injection' => ['SELECT * FROM invoices; revenue?'],
            'fp_broad_today' => ['اليوم كان طويل'],
            'fp_broad_available' => ['فستان راقد'],
            'fp_broad_best' => ['مين الأفضل؟'],
            'fp_broad_orders' => ['عندي طلب'],
            'fp_broad_perf' => ['الأداء مهم'],
        ];
    }

    public function test_all_intents_registered(): void
    {
        $intents = $this->router->intents();
        $this->assertContains('revenue_summary', $intents);
        $this->assertContains('active_reservations', $intents);
        $this->assertContains('late_returns', $intents);
        $this->assertContains('pending_deliveries', $intents);
        $this->assertContains('active_customers', $intents);
        $this->assertContains('inactive_dresses', $intents);
        $this->assertContains('business_snapshot', $intents);
        $this->assertContains('business_health', $intents);
        $this->assertContains('daily_brief', $intents);
        $this->assertCount(9, $intents);
    }

    public function test_tools_for_intent_mapping(): void
    {
        $this->assertSame(['revenue_summary'], $this->router->toolsForIntent('revenue_summary'));
        $this->assertSame(['business_snapshot'], $this->router->toolsForIntent('business_snapshot'));
        $this->assertSame([], $this->router->toolsForIntent('nonexistent'));
    }

    public function test_arabic_diacritics_normalized(): void
    {
        $intents = $this->router->route('كَمُ الإِيرَادَاتِ');
        $this->assertContains('revenue_summary', $intents);
    }

    public function test_egyptian_arabic_variants(): void
    {
        $intents = $this->router->route('دخل النهارده كام؟');
        $this->assertContains('revenue_summary', $intents);
        $intents = $this->router->route('شلون الشغل النهارده؟');
        $this->assertContains('business_snapshot', $intents);
    }
}
