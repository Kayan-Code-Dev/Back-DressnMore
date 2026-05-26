<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Permission;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantPurchaseOrderTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $purchaseOrderUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->purchaseOrderUser = $this->createTenantUserWithPermissions([
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.delete',
            'supplier_payments.create',
        ]);
    }

    public function test_tenant_user_can_list_purchase_orders(): void
    {
        $supplier = $this->createSupplier();
        PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_order_number' => 'PO-20260526-0001',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'total' => 100,
            'remaining_amount' => 100,
        ]);

        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->getJson('/api/tenant/purchase-orders', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tenant_user_can_create_purchase_order_with_items(): void
    {
        $supplier = $this->createSupplier();
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'items' => [
                ['item_name' => 'Fabric Roll', 'quantity' => 2, 'unit_price' => 100],
                ['item_name' => 'Buttons Pack', 'quantity' => 1, 'unit_price' => 50],
            ],
            'discount' => 20,
            'tax' => 10,
            'notes' => 'New purchase order',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Purchase order created')
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.items.0.item_name', 'Fabric Roll');

        $this->assertMatchesRegularExpression('/^PO-\d{8}-\d{4}$/', (string) $response->json('data.purchase_order_number'));
    }

    public function test_purchase_order_totals_are_calculated_correctly(): void
    {
        $supplier = $this->createSupplier();
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'items' => [
                ['item_name' => 'Fabric Roll', 'quantity' => 2, 'unit_price' => 100],
                ['item_name' => 'Buttons Pack', 'quantity' => 1, 'unit_price' => 50],
            ],
            'discount' => 20,
            'tax' => 10,
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '250.00')
            ->assertJsonPath('data.total', '240.00')
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.remaining_amount', '240.00')
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_CONFIRMED);
    }

    public function test_tenant_user_can_update_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id);
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->putJson("/api/tenant/purchase-orders/{$purchaseOrderId}", [
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-27',
            'items' => [
                ['item_name' => 'Updated Fabric', 'quantity' => 3, 'unit_price' => 80],
            ],
            'discount' => 10,
            'tax' => 5,
            'notes' => 'Updated order',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Purchase order updated')
            ->assertJsonPath('data.items.0.item_name', 'Updated Fabric')
            ->assertJsonPath('data.total', '235.00');
    }

    public function test_purchase_order_status_changes_with_payments(): void
    {
        $supplier = $this->createSupplier();
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 100);
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 40,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $this->getJson("/api/tenant/purchase-orders/{$purchaseOrderId}", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_PARTIALLY_PAID);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 60,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $this->getJson("/api/tenant/purchase-orders/{$purchaseOrderId}", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_PAID)
            ->assertJsonPath('data.remaining_amount', '0.00');
    }

    public function test_tenant_user_can_delete_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id);
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->deleteJson("/api/tenant/purchase-orders/{$purchaseOrderId}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Purchase order deleted');

        $this->assertSoftDeleted('purchase_orders', ['id' => $purchaseOrderId], 'tenant');
    }

    public function test_search_filter_works(): void
    {
        $supplierA = Supplier::query()->create(['name' => 'Supplier Alpha', 'status' => 'active']);
        $supplierB = Supplier::query()->create(['name' => 'Supplier Beta', 'status' => 'active']);

        PurchaseOrder::query()->create([
            'supplier_id' => $supplierA->id,
            'purchase_order_number' => 'PO-20260526-1111',
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'total' => 100,
            'remaining_amount' => 100,
            'order_date' => '2026-05-26',
        ]);
        PurchaseOrder::query()->create([
            'supplier_id' => $supplierB->id,
            'purchase_order_number' => 'PO-20260526-2222',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'total' => 200,
            'remaining_amount' => 200,
            'order_date' => '2026-05-30',
        ]);

        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->getJson(
            "/api/tenant/purchase-orders?search=1111&supplier_id={$supplierA->id}&status=confirmed&date_from=2026-05-25&date_to=2026-05-27",
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.purchase_order_number', 'PO-20260526-1111');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/purchase-orders', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createSupplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Main Supplier',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);
    }

    private function createPurchaseOrderViaApi(int $supplierId, float $unitPrice = 100): int
    {
        Sanctum::actingAs($this->purchaseOrderUser, ['*']);

        $response = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplierId,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'items' => [
                ['item_name' => 'Fabric', 'quantity' => 1, 'unit_price' => $unitPrice],
            ],
            'discount' => 0,
            'tax' => 0,
        ], $this->tenantHeaders());

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-purchase-orders.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-purchase-orders.sqlite';

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

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

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
