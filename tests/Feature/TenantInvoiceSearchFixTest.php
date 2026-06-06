<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantTokenBinding;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantInvoiceSearchFixTest extends TestCase
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
        $this->tenant = $this->createTenant();
        $this->withoutMiddleware(EnsureTenantTokenBinding::class);
        $this->user = $this->createTenantUserWithPermissions(['invoices.view', 'invoices.create']);
    }

    public function test_create_rental_invoice_accepts_rental_type_alias(): void
    {
        $customer = Customer::query()->create(['name' => 'Rental Customer', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => 'rental',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'rent_start_date' => '2026-06-10',
            'rent_end_date' => '2026-06-12',
            'items' => [
                ['description' => 'Rental item', 'quantity' => 1, 'unit_price' => 200],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.type', Invoice::TYPE_RENT);
    }

    public function test_invoice_search_matches_customer_name_and_phone(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Nour Customer',
            'phone' => '01099887766',
            'status' => 'active',
        ]);

        Invoice::query()->create([
            'invoice_number' => 'INV-SRCH-001',
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 300,
            'paid_amount' => 0,
            'remaining_amount' => 300,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/tenant/invoices?search=nour', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-SRCH-001');

        $this->getJson('/api/tenant/invoices?search=01099887766', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-SRCH-001');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-invoice-search-fix.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoice-search-fix.sqlite';

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

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function createTenantUserWithPermissions(array $permissionKeys): User
    {
        $role = Role::query()->create(['name' => 'Role '.uniqid(), 'slug' => 'role-'.uniqid()]);
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $role->permissions()->sync($permissionIds);

        $user = User::query()->create([
            'name' => 'Tenant User '.uniqid(),
            'email' => uniqid().'@tenant.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
        ];
    }
}
