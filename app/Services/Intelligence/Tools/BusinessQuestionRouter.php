<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

final class BusinessQuestionRouter
{
    private const MIN_SCORE = 2;

    private array $routes;

    public function __construct()
    {
        $this->routes = [
            'revenue_summary' => [
                'keywords' => [
                    'ايرادات', 'الايرادات', 'مبالغ محصلة', 'المبالغ المحصلة',
                    'فلوس', 'دخل', 'الدخل', 'وارد', 'حصلنا', 'تحصيل',
                    'مبيعات', 'المبيعات', 'مبيع', 'كم بعنا', 'ربح',
                    'النهاردة', 'النهارده', 'اليوم', 'dakhli', 'dakhl',
                    'revenue', 'revenues', 'sales', 'income', 'earnings',
                    'how much did we make', 'how much money', 'total sales',
                    'turnover', 'proceeds', 'collection', 'collected',
                    'money made', 'cash collected', 'today revenue',
                
                    'دخل اليوم كام',
                    'دخل النهاردة كام',
                    'دخلنا كام',
                    'دخلنا كام اليوم',
                    'إيراد النهاردة كام',
                    'إيراد اليوم كام',
                    'إيرادات النهاردة',
                    'عملنا كام النهاردة',
                    'عملنا كام اليوم',
                    'عملنا كام',
                    'اتحصل كام اليوم',
                    'اتحصل كام',
                    'تحصيلات اليوم كام',
                    'المبالغ المحصلة كام',
                    'المحصل كام',
                    'محصلناش كام',
                    'مبيعات اليوم كام',
                    'مبيعات النهاردة كام',
                    'بعنا كام',
                    'كام فلوس اليوم',
                    'فلوس النهاردة كام',
                    'جمعنا كام',
                    'كاش اليوم كام',
                    'صندوق اليوم كام',
                    'دخلنا النهاردة',
                ],
            ],
            'active_reservations' => [
                'keywords' => [
                    'حجوزات نشطة', 'حجوزات حالية', 'حجز نشط', 'حجز حالي',
                    'مستأجر', 'مؤجرة', 'مستاجرين', 'المستأجرات',
                    'فساتين مؤجرة', 'الفساتين المؤجرة', 'فساتين مستأجرة', 'عقود ايجار',
                    'ايجار نشط', 'حجوزات الايجار', 'كم حجز',
                    'active reservations', 'active rentals', 'currently rented',
                    'rented dresses', 'on rent', 'active bookings',
                    'rental contract', 'rented out', 'rented items',
                
                    'عندي كام حجز',
                    'عندي كام فستان متحجز',
                    'الحجوزات النهاردة كام',
                    'في حجوزات نشطة',
                    'كام فستان متحجز',
                    'حجوزاتنا كام',
                    'عقود الايجار كام',
                    'مستاجرين كام',
                    'كام حجز نشط',
                    'الفساتين المؤجرة كام',
                    'فيه حجوزات',
                    'عدد الحجوزات كام',
                ],
            ],
            'late_returns' => [
                'keywords' => [
                    'متأخر', 'متأخرة', 'متأخرين', 'التأخير', 'التأخير في الرد', 'تأخير الرد',
                    'لم يرجع', 'لم ترد', 'مؤجلة الرد', 'مرتجعات متأخرة',
                    'غرامة تأخير', 'مخالفة تأخير', 'تأخر العميل',
                    'late return', 'overdue rental', 'past due', 'not returned',
                    'return late', 'returned late', 'overdue return',
                    'late fee', 'rental overdue', 'past return date',
                
                    'في مرتجعات متأخرة',
                    'مين متأخر في الترجيع',
                    'في فساتين لسه ما رجعتش',
                    'عندي تأخير في المرتجعات',
                    'متأخرين في التسليم',
                    'مين لسه ما رجعش',
                    'فساتين متأخرة',
                    'عقود متأخرة',
                    'موعد الرد فات',
                    'مرتجع متأخر',
                ],
            ],
            'pending_deliveries' => [
                'keywords' => [
                    'توصيل معلق', 'توصيلات معلقة', 'مطلوب توصيل',
                    'تسليم معلق', 'تسليمات معلقة', 'لم توصل',
                    'توصيل النهاردة', 'تسليم النهاردة', 'طلبات التوصيل',
                    'السليمات المطلوبة', 'مطلوبة النهاردة', 'التوصيلات',
                    'pending delivery', 'pending deliveries', 'to deliver',
                    'deliveries pending', 'deliveries are pending',
                    'undelivered', 'needs delivery', 'out for delivery',
                    'delivery pending', 'scheduled delivery',
                
                    'عندي تسليمات إيه النهاردة',
                    'في فساتين لازم تتسلم',
                    'التسليمات المعلقة كام',
                    'مين المفروض يستلم اليوم',
                    'تسليمات اليوم',
                    'توصيلات النهاردة',
                    'طلبات التسليم',
                    'مين مستلم اليوم',
                    'فساتين للتسليم',
                    'معلق للتسليم',
                    'مواعيد التسليم اليوم',
                    'التسليمات النهاردة',
                ],
            ],
            'active_customers' => [
                'keywords' => [
                    'افضل عملاء', 'افضل العملاء', 'افضل زباين', 'افضل الزباين',
                    'اكثر عملاء', 'اكثر العملاء', 'اكثر زباين', 'اكثر الزباين',
                    'عملاء نشطين', 'زباين نشطين', 'top customer',
                    'vip', 'عملاء مميزين',
                    'top customer', 'best customer', 'most frequent customer',
                    'regular customer', 'loyal customer', 'active customers',
                    'best client', 'top client', 'customer ranking',
                ],
            ],
            'inactive_dresses' => [
                'keywords' => [
                    'فساتين متاحة', 'الفساتين المتاحة', 'فساتين راكدة', 'الفساتين الراكدة',
                    'مخزون فساتين', 'مخزون الفساتين', 'فستان متاح', 'الفستان المتاح',
                    'available dresses', 'dresses available', 'dress inventory',
                    'in stock', 'unsold dresses', 'unrented dresses',
                    'idle dresses', 'available inventory', 'what dresses',
                
                    'إيه الفساتين الراكدة',
                    'في فساتين ما اتحجزتش من فترة',
                    'المخزون وضعه إيه',
                    'إيه الفساتين اللي مش بتتحرك',
                    'مخزون راكد',
                    'فساتين ما بتتأجرش',
                    'فساتين متعبة',
                    'مخزون عاطل',
                    'فساتين قاعدة',
                    'مين الفساتين اللي بتقعد',
                ],
            ],
            'business_snapshot' => [
                'keywords' => [
                    'وضع الشغل', 'وضع العمل', 'كيف الامور', 'شلون الوضع',
                    'نظرة عامة', 'ملخص العمل', 'حالة العمل', 'تقرير عام',
                    'شلون الشغل', 'كيف الشغل',
                    'how is business', 'business overview', 'business summary',
                    'how are we doing', 'business snapshot', 'quick summary',
                    'work status', 'general status',
                
                    'كيف وضع الشغل',
                    'الدنيا ماشية إزاي',
                    'اديني ملخص للشغل',
                    'إيه وضع الأتيليه',
                    'أركز على إيه النهاردة',
                    'إيه أهم حاجة أعملها اليوم',
                    'لوحة الملخص',
                    'ملخص النشاط',
                    'كيف الحال في الورشة',
                    'ملخص الشغل',
                    'الأوضاع في الأتيليه',
                    'كيف أمور الشغل',
                    'اديني نظرة عامة',
                    'شوفلي الشغل',
                ],
            ],
            'business_health' => [
                'keywords' => [
                    'صحة العمل', 'مؤشرات الاداء', 'اداء العمل', 'kpi',
                    'تحليل الاداء', 'health check',
                    'business health', 'business metrics', 'kpi', 'performance',
                    'health check', 'business analysis', 'performance indicators',
                    'trend analysis', 'business trend',
                ],
            ],
            'daily_brief' => [
                'keywords' => [
                    'تقرير اليوم', 'ملخص اليوم', 'إيه اللي حصل النهاردة',
                    'وضع اليوم', 'briefing', 'brief اليوم',
                    'صباح الخير', 'good morning', 'morning report',
                    'daily brief', 'today report', 'what happened today',
                    'today summary', 'morning brief', 'daily report',
                
                    'أركز على إيه النهاردة',
                    'إيه أهم حاجة أعملها اليوم',
                    'موجز النهاردة',
                    'أولويات اليوم',
                    'إيه اللي مستعجل اليوم',
                    'تنبيهات اليوم',
                    'ملخص النهاردة',
                    'إيه الجديد اليوم',
                    'أهم حاجة النهاردة',
                ],
            ],
        ];
    }

    public function route(string $question): array
    {
        $question = $this->stripInjectionPatterns($question);
        $normalized = $this->normalize($question);
        $scores = [];
        foreach ($this->routes as $intent => $config) {
            $score = 0;
            foreach ($config['keywords'] as $keyword) {
                $normKeyword = $this->normalize($keyword);
                if ($normKeyword !== '' && str_contains($normalized, $normKeyword)) {
                    $wordCount = count(array_filter(explode(' ', $normKeyword)));
                    $score += min($wordCount, 3);
                }
            }
            if ($score >= self::MIN_SCORE) { $scores[$intent] = $score; }
        }
        arsort($scores);
        return array_keys(array_filter($scores, fn (int $s) => $s >= self::MIN_SCORE));
    }

    public function toolsForIntent(string $intent): array
    {
        return match ($intent) {
            'revenue_summary' => ['revenue_summary'],
            'active_reservations' => ['active_reservations'],
            'late_returns' => ['late_returns'],
            'pending_deliveries' => ['pending_deliveries'],
            'active_customers' => ['active_customers'],
            'inactive_dresses' => ['inactive_dresses'],
            'business_snapshot' => ['business_snapshot'],
            'business_health' => ['business_health'],
            'daily_brief' => ['daily_brief'],
            default => [],
        };
    }

    public function intents(): array { return array_keys($this->routes); }
    public function hasMatch(string $question): bool { return $this->route($question) !== []; }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $tashkeel = '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u';
        $text = preg_replace($tashkeel, '', $text) ?? $text;
        $text = str_replace(["\u{0623}", "\u{0625}", "\u{0622}", "\u{0671}"], "\u{0627}", $text);
        $text = str_replace("\u{0649}", "\u{064A}", $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function stripInjectionPatterns(string $text): string
    {
        $patterns = [
            '/app\\\\services\\\\intelligence\\\\tools\\\\\w+/i',
            '/business\w+tool/i', '/safe\w+tool/i', '/revenue\w+tool/i',
            '/\.php\b/i', '/namespace\s+app/i', '/use\s+app/i',
            '/\w*[A-Z][a-z]+[A-Z]\w*/',
        ];
        return preg_replace($patterns, '', $text) ?? $text;
    }
}
