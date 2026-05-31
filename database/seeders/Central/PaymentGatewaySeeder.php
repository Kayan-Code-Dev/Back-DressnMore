<?php

namespace Database\Seeders\Central;

use App\Models\Central\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'البنك الأهلي السعودي',
                'type' => 'bank',
                'account_holder' => 'شركة درسن مور للتقنية',
                'account_number' => '1234567890',
                'bank_name' => 'البنك الأهلي السعودي (SNB)',
                'iban' => 'SA12 1000 0000 1234 5678 9012',
                'instructions' => 'يرجى تحويل المبلغ مع ذكر رقم الفاتورة في خانة ملاحظات التحويل.',
                'is_active' => true,
                'display_order' => 1,
                'usage_count' => 0,
            ],
            [
                'name' => 'فودافون كاش',
                'type' => 'vodafone_cash',
                'account_holder' => 'Dress n More',
                'account_number' => '01012345678',
                'instructions' => 'أرسل المبلغ على رقم فودافون كاش المذكور ثم أكّد الدفع من واجهة الاشتراك.',
                'is_active' => true,
                'display_order' => 2,
                'usage_count' => 0,
            ],
            [
                'name' => 'انستاباي',
                'type' => 'instapay',
                'account_holder' => 'Dress n More',
                'account_number' => 'dressnmore@instapay',
                'instructions' => 'ابحث عن @dressnmore في تطبيق InstaPay وأرسل المبلغ مباشرة.',
                'is_active' => true,
                'display_order' => 3,
                'usage_count' => 0,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::query()->updateOrCreate(
                ['name' => $gateway['name']],
                $gateway
            );
        }
    }
}
