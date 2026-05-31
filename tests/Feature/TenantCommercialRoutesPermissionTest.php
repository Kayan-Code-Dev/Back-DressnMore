<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
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

class TenantCommercialRoutesPermissionTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
    }

    public function test_sales_invoices_list_requires_invoices_view_permission(): void
    {
        $user = $this->createTenantUserWithPermissions([]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/tenant/sales/invoices', $this->tenantHeaders())
            ->assertStatus(403);

        $authorized = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($authorized, ['*']);

        $this->getJson('/api/tenant/sales/invoices', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_sales_invoice_create_requires_invoices_create_permission(): void
    {
        $customer = Customer::query()->create(['name' => 'Buyer', 'status' => 'active']);
        $viewer = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($viewer, ['*']);

        $this->postJson('/api/tenant/sales/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Dress', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $this->tenantHeaders())->assertStatus(403);

        $creator = $this->createTenantUserWithPermissions(['invoices.create']);
        Sanctum::actingAs($creator, ['*']);

        $this->postJson('/api/tenant/sales/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Dress', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $this->tenantHeaders())->assertCreated();
    }

    public function test_rental_orders_list_requires_invoices_view_permission(): void
    {
        $user = $this->createTenantUserWithPermissions([]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/tenant/orders/rental', $this->tenantHeaders())
            ->assertStatus(403);

        $authorized = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($authorized, ['*']);

        $this->getJson('/api/tenant/orders/rental', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_deliveries_list_requires_invoice_delivery_view_permission(): void
    {
        $user = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/tenant/deliveries', $this->tenantHeaders())
            ->assertStatus(403);

        $authorized = $this->createTenantUserWithPermissions(['invoice_delivery.view']);
        Sanctum::actingAs($authorized, ['*']);

        $this->getJson('/api/tenant/deliveries', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_returns_overdue_requires_invoice_delivery_view_permission(): void
    {
        $user = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/tenant/returns/overdue', $this->tenantHeaders())
            ->assertStatus(403);

        $authorized = $this->createTenantUserWithPermissions(['invoice_delivery.view']);
        Sanctum::actingAs($authorized, ['*']);

        $this->getJson('/api/tenant/returns/overdue', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_returned_rental_is_not_listed_as_overdue(): void
    {
        Invoice::query()->create([
            'invoice_number' => 'RENT-OVERDUE-1',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_DELIVERED,
            'rent_start_date' => CarbonImmutable::now()->subDays(10),
            'rent_end_date' => CarbonImmutable::now()->subDays(5),
            'return_date' => CarbonImmutable::now()->subDays(5),
            'total' => 500,
            'remaining_amount' => 0,
            'paid_amount' => 500,
        ]);

        Invoice::query()->create([
            'invoice_number' => 'RENT-RETURNED-1',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_RETURNED,
            'rent_start_date' => CarbonImmutable::now()->subDays(10),
            'rent_end_date' => CarbonImmutable::now()->subDays(5),
            'return_date' => CarbonImmutable::now()->subDays(5),
            'total' => 500,
            'remaining_amount' => 0,
            'paid_amount' => 500,
        ]);

        $user = $this->createTenantUserWithPermissions(['invoice_delivery.view']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/tenant/returns/overdue', $this->tenantHeaders());

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_create_rental_invoice_via_invoices_endpoint(): void
    {
        $customer = Customer::query()->create(['name' => 'Renter', 'status' => 'active']);
        $category = DressCategory::query()->create(['name' => 'Evening', 'status' => 'active']);
        $dress = Dress::query()->create([
            'code' => 'DR-RENT-1',
            'name' => 'Evening Gown',
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $category->id,
            'status' => 'available',
            'rental_price' => 800,
        ]);

        $user = $this->createTenantUserWithPermissions(['invoices.create']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'customer_id' => $customer->id,
            'rent_start_date' => '2026-07-01',
            'rent_end_date' => '2026-07-05',
            'delivery_date' => '2026-07-01',
            'return_date' => '2026-07-05',
            'days_of_rent' => 5,
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 800],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', Invoice::TYPE_RENT);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-commercial-routes.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-commercial-routes.sqlite';

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
        $role = Role::query()->create([
            'name' => 'Role '.uniqid(),
            'slug' => 'role-'.uniqid(),
        ]);

        if ($permissionKeys !== []) {
            $permissionIds = Permission::query()
                ->whereIn('key', $permissionKeys)
                ->pluck('id')
                ->all();
            $role->permissions()->sync($permissionIds);
        }

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
            'X-Tenant' => $this->tenant->slug,
            'Accept' => 'application/json',
        ];
    }
}
