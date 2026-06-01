<?php

namespace Tests\Feature;

use App\Models\Tenant\Account;
use Database\Seeders\Tenant\AccountSeeder;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAccountSeederTest extends TestCase
{
    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareTenantDatabase();
        $this->runTenantMigrations();
    }

    public function test_phase2_chart_accounts_are_seeded_with_expected_types(): void
    {
        $this->seedAccounts();

        $expected = [
            '2100' => 'liability',
            '4200' => 'revenue',
            '4210' => 'revenue',
            '4220' => 'revenue',
        ];

        foreach ($expected as $code => $type) {
            $account = Account::query()->where('code', $code)->first();
            $this->assertNotNull($account, "Account {$code} should exist after seeding.");
            $this->assertSame($type, $account->type);
        }

        $this->assertDatabaseHas('accounts', [
            'code' => '2100',
            'name' => 'ودائع تأمين قابلة للاسترداد',
        ], 'tenant');
    }

    public function test_account_seeder_is_idempotent_and_does_not_duplicate_codes(): void
    {
        $this->seedAccounts();
        $countAfterFirst = Account::query()->count();

        $this->seedAccounts();
        $countAfterSecond = Account::query()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);

        foreach (AccountSeeder::PHASE2_ACCOUNT_CODES as $code) {
            $this->assertSame(
                1,
                Account::query()->where('code', $code)->count(),
                "Expected exactly one row for account code {$code}.",
            );
        }
    }

    public function test_tenant_role_permission_seeder_includes_chart_accounts(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => TenantRolePermissionSeeder::class,
            '--force' => true,
        ]);

        foreach (AccountSeeder::PHASE2_ACCOUNT_CODES as $code) {
            $this->assertDatabaseHas('accounts', ['code' => $code], 'tenant');
        }
    }

    private function prepareTenantDatabase(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->tenantDatabasePath = $testingPath.'/tenant-account-seeder.sqlite';
        @unlink($this->tenantDatabasePath);
        touch($this->tenantDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => storage_path('framework/testing/central-account-seeder.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('central');
        DB::purge('tenant');
    }

    private function runTenantMigrations(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function seedAccounts(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => AccountSeeder::class,
            '--force' => true,
        ]);
    }
}
