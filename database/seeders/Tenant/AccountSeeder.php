<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /** @var list<string> Phase 2 commercial accounting codes (deposit liability + fee revenue). */
    public const PHASE2_ACCOUNT_CODES = ['2100', '4200', '4210', '4220'];

    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'الصندوق', 'type' => 'asset'],
            ['code' => '1010', 'name' => 'البنك', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'العملاء', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'الموردون', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'ودائع تأمين قابلة للاسترداد', 'type' => 'liability'],
            ['code' => '3000', 'name' => 'رأس المال', 'type' => 'equity'],
            ['code' => '4000', 'name' => 'إيرادات الإيجار', 'type' => 'revenue'],
            ['code' => '4100', 'name' => 'إيرادات البيع', 'type' => 'revenue'],
            ['code' => '4200', 'name' => 'إيرادات غرامة التأخير', 'type' => 'revenue'],
            ['code' => '4210', 'name' => 'إيرادات أضرار', 'type' => 'revenue'],
            ['code' => '4220', 'name' => 'إيرادات تنظيف', 'type' => 'revenue'],
            ['code' => '5000', 'name' => 'مصروفات تشغيل', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'مصروفات إيجار', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'مصروفات رواتب', 'type' => 'expense'],
        ];

        $codes = array_column($accounts, 'code');
        if (count($codes) !== count(array_unique($codes))) {
            throw new \InvalidArgumentException('Duplicate account codes defined in AccountSeeder.');
        }

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
