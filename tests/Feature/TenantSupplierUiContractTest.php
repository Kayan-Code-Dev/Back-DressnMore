<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\DressCategory;
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

class TenantSupplierUiContractTest extends TestCase
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
        $this->user = $this->createTenantUserWithPermissions([
            'suppliers.view',
            'suppliers.create',
            'suppliers.export',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.return',
            'purchase_orders.export',
        ]);
    }

    public function test_supplier_code_purchase_order_return_and_exports(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $supplierResponse = $this->postJson('/api/tenant/suppliers', [
            'code' => 'SUP-001',
            'name' => 'UI Supplier',
            'phone' => '010',
            'address' => 'Supplier Address',
            'status' => 'active',
        ], $this->tenantHeaders());
        $supplierResponse->assertCreated()->assertJsonPath('data.code', 'SUP-001');
        $supplierId = (int) $supplierResponse->json('data.id');

        $branch = Branch::query()->create(['name' => 'PO Branch', 'status' => 'active']);
        $category = DressCategory::query()->create(['name' => 'PO Cat', 'status' => 'active']);
        $subcat = DressCategory::query()->create(['name' => 'PO Sub', 'parent_id' => $category->id, 'status' => 'active']);

        $poResponse = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplierId,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcat->id,
            'type' => 'fabric',
            'status' => 'confirmed',
            'items' => [
                ['item_name' => 'Fabric Roll', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $this->tenantHeaders());
        $poResponse->assertCreated()->assertJsonPath('data.type', 'fabric');
        $purchaseOrderId = (int) $poResponse->json('data.id');

        $this->postJson("/api/tenant/purchase-orders/{$purchaseOrderId}/return", [
            'return_notes' => 'Returned partially',
        ], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.is_returned', true);

        $supplierExport = $this->get('/api/tenant/suppliers/export', $this->tenantHeaders());
        $supplierExport->assertOk();
        $this->assertStringContainsString('suppliers.csv', (string) $supplierExport->headers->get('content-disposition'));
        $this->assertStringContainsString('UI Supplier', $supplierExport->streamedContent());

        $poExport = $this->get('/api/tenant/purchase-orders/export', $this->tenantHeaders());
        $poExport->assertOk();
        $this->assertStringContainsString('purchase-orders.csv', (string) $poExport->headers->get('content-disposition'));
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-suppliers-ui.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-suppliers-ui.sqlite';
        @unlink($this->centralDatabasePath);
        @unlink($this->tenantDatabasePath);
        touch($this->centralDatabasePath);
        touch($this->tenantDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite', 'database' => $this->centralDatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite', 'database' => $this->tenantDatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
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
     * @return array<string,string>
     */
    private function tenantHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => $this->tenant->slug];
    }
}
