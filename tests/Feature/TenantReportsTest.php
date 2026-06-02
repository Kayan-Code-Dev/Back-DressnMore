<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantReportsTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant('reports-qa');
        $this->user = $this->createOwnerUser();
        Sanctum::actingAs($this->user);
    }

    public function test_reports_catalog_lists_all_modules(): void
    {
        $this->getJson('/api/tenant/reports/catalog', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reports', 'export_formats']]);
    }

    public function test_sales_report_returns_summary(): void
    {
        Invoice::query()->create([
            'invoice_number' => 'INV-001',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 1500,
            'paid_amount' => 500,
            'remaining_amount' => 1000,
        ]);

        $this->getJson('/api/tenant/reports/sales?period=month', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.total_sales', 1500.0)
            ->assertJsonPath('data.invoices_count', 1);
    }

    public function test_rental_report_endpoint_works(): void
    {
        $this->getJson('/api/tenant/reports/rental?period=month', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonStructure(['data' => ['total', 'active', 'returned', 'overdue', 'revenue']]);
    }

    public function test_customers_report_endpoint_works(): void
    {
        Customer::query()->create([
            'customer_code' => 'CUS-001',
            'name' => 'Report Customer',
            'phone' => '0500000000',
            'status' => 'active',
        ]);

        $this->getJson('/api/tenant/reports/customers', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_sales_report_csv_export(): void
    {
        Invoice::query()->create([
            'invoice_number' => 'INV-002',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 800,
            'paid_amount' => 800,
            'remaining_amount' => 0,
        ]);

        $response = $this->get('/api/tenant/reports/sales?period=month&export=csv', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    private function tenantHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
        ];
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-reports.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-reports.sqlite';
        @unlink($this->centralDatabasePath);
        @unlink($this->tenantDatabasePath);
        touch($this->centralDatabasePath);
        touch($this->tenantDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => $this->centralDatabasePath,
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

    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function seedTenantPermissions(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => TenantRolePermissionSeeder::class,
            '--force' => true,
        ]);
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Reports Tenant',
            'slug' => $slug,
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(30),
        ]);
    }

    private function createOwnerUser(): User
    {
        $ownerRole = Role::query()->where('slug', 'owner')->first();
        $user = User::query()->create([
            'name' => 'Reports Owner',
            'email' => 'owner@reports.test',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        if ($ownerRole) {
            $user->roles()->sync([$ownerRole->id]);
        }

        return $user;
    }
}
