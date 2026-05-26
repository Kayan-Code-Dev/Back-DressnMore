<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantDemoSeedCommandTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->createTenant();
    }

    public function test_demo_tenant_seed_command_seeds_dataset_for_existing_tenant(): void
    {
        Artisan::call('demo:tenant-seed', ['tenantSlug' => 'demo']);

        $this->assertDatabaseHas('users', [
            'email' => 'demo.owner+demo@dressnmore.test',
        ], 'tenant');
        $this->assertDatabaseHas('users', [
            'email' => 'demo.manager+demo@dressnmore.test',
        ], 'tenant');
        $this->assertDatabaseCount('invoices', 3, 'tenant');
        $this->assertSame(
            3,
            Customer::query()->where('national_id', 'like', 'DEMO-DEMO-CUST-%')->count()
        );
        $this->assertSame(
            1,
            PurchaseOrder::query()->where('purchase_order_number', 'DEMO-DEMO-PO-001')->count()
        );
        $this->assertTrue(
            CashMovement::query()->where('reference', 'DEMO-DEMO-MANUAL-OPENING')->exists()
        );
    }

    public function test_demo_tenant_seed_command_can_be_run_multiple_times_without_duplicate_demo_records(): void
    {
        Artisan::call('demo:tenant-seed', ['tenantSlug' => 'demo']);
        Artisan::call('demo:tenant-seed', ['tenantSlug' => 'demo']);

        $this->assertSame(
            1,
            User::query()->where('email', 'demo.owner+demo@dressnmore.test')->count()
        );
        $this->assertSame(
            3,
            Invoice::query()->where('invoice_number', 'like', 'DEMO-DEMO-INV-%')->count()
        );
        $this->assertSame(
            2,
            InvoicePayment::query()->where('reference', 'like', 'DEMO-DEMO-PAY-%')->count()
        );
        $this->assertSame(
            1,
            SupplierPayment::query()->where('reference', 'DEMO-DEMO-SUPPAY-001')->count()
        );
        $this->assertSame(
            1,
            CashMovement::withTrashed()->where('reference', 'DEMO-DEMO-MANUAL-OPENING')->count()
        );
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-demo-seed.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-demo-seed.sqlite';

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
        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function createTenant(): void
    {
        Tenant::query()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(30),
        ]);
    }
}
