<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Permission;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantPurchaseOrderTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $purchaseOrderUser;

    private ?string $tenantToken = null;

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
            'suppliers.view',
            'purchase_orders.return',
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

        $this->actingAsTenant($this->purchaseOrderUser);

        $response = $this->getJson('/api/tenant/purchase-orders', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tenant_user_can_create_purchase_order_with_items(): void
    {
        $supplier = $this->createSupplier();
        $this->actingAsTenant($this->purchaseOrderUser);

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

    public function test_tenant_user_can_create_purchase_order_with_item_code_category_subcategory_and_deposit(): void
    {
        $supplier = $this->createSupplier();
        $branch = $this->createBranch();
        [$category, $subcategory] = $this->createDressCategoryPair();
        $this->actingAsTenant($this->purchaseOrderUser);

        $response = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'expected_delivery_date' => '2026-06-01',
            'deposit_amount' => 75,
            'items' => [[
                'code' => 'DN-PO-001',
                'dress_category_id' => $category->id,
                'dress_subcategory_id' => $subcategory->id,
                'item_name' => 'Evening Dress',
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.items.0.code', 'DN-PO-001')
            ->assertJsonPath('data.items.0.dress_category_id', $category->id)
            ->assertJsonPath('data.items.0.dress_subcategory_id', $subcategory->id)
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.deposit_amount', '75.00')
            ->assertJsonPath('data.paid_amount', '75.00')
            ->assertJsonPath('data.remaining_amount', '125.00')
            ->assertJsonPath('data.expected_delivery_date', '2026-06-01')
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_PARTIALLY_PAID);

        $purchaseOrderId = (int) $response->json('data.id');

        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 75,
            'reference' => 'DEPOSIT-'.$response->json('data.purchase_order_number'),
        ], 'tenant');
        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_SUPPLIER_PAYMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 75,
        ], 'tenant');
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => JournalEntry::SOURCE_SUPPLIER_PAYMENT,
            'total_debit' => 75,
            'total_credit' => 75,
        ], 'tenant');
        $this->assertSame('125.00', (string) $supplier->refresh()->current_balance);
    }

    public function test_purchase_order_totals_are_calculated_correctly(): void
    {
        $supplier = $this->createSupplier();
        $this->actingAsTenant($this->purchaseOrderUser);

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

    public function test_receive_purchase_order_creates_dress_in_branch(): void
    {
        $supplier = $this->createSupplier();
        $branch = $this->createBranch();
        [$category, $subcategory] = $this->createDressCategoryPair();
        $this->actingAsTenant($this->purchaseOrderUser);

        $createResponse = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'items' => [[
                'code' => 'DN-PO-RECEIVE-001',
                'dress_category_id' => $category->id,
                'dress_subcategory_id' => $subcategory->id,
                'item_name' => 'Received Dress',
                'quantity' => 1,
                'unit_price' => 150,
            ]],
        ], $this->tenantHeaders());
        $createResponse->assertCreated();

        $purchaseOrderId = (int) $createResponse->json('data.id');
        $response = $this->postJson(
            "/api/tenant/purchase-orders/{$purchaseOrderId}/receive",
            ['received_at' => '2026-05-28 10:30:00'],
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Purchase order received')
            ->assertJsonPath('data.inventory_received', true)
            ->assertJsonPath('data.items.0.dress.code', 'DN-PO-RECEIVE-001')
            ->assertJsonPath('data.items.0.dress.branch_id', $branch->id);

        $dressId = (int) $response->json('data.items.0.dress_id');
        $dress = Dress::query()->findOrFail($dressId);

        $this->assertSame('DN-PO-RECEIVE-001', $dress->code);
        $this->assertSame($branch->id, (int) $dress->branch_id);
        $this->assertSame($category->id, (int) $dress->dress_category_id);
        $this->assertSame($subcategory->id, (int) $dress->dress_subcategory_id);
        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_CREATED,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrderId,
            'to_branch_id' => $branch->id,
        ], 'tenant');
    }

    public function test_tenant_user_can_update_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id);
        $this->actingAsTenant($this->purchaseOrderUser);

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
        $this->actingAsTenant($this->purchaseOrderUser);

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

    public function test_supplier_balance_and_account_purchase_order_fields_update_after_deposit(): void
    {
        $supplier = $this->createSupplier();
        $branch = $this->createBranch();
        [$category, $subcategory] = $this->createDressCategoryPair();
        $this->actingAsTenant($this->purchaseOrderUser);

        $createResponse = $this->postJson('/api/tenant/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'expected_delivery_date' => '2026-06-05',
            'deposit_amount' => 60,
            'items' => [[
                'code' => 'DN-ACCOUNT-001',
                'dress_category_id' => $category->id,
                'dress_subcategory_id' => $subcategory->id,
                'item_name' => 'Account Dress',
                'quantity' => 1,
                'unit_price' => 200,
            ]],
        ], $this->tenantHeaders());
        $createResponse->assertCreated();

        $purchaseOrderId = (int) $createResponse->json('data.id');

        $this->assertSame('140.00', (string) $supplier->refresh()->current_balance);
        $this->assertSame(60.0, (float) SupplierPayment::query()->where('purchase_order_id', $purchaseOrderId)->sum('amount'));

        $this->postJson(
            "/api/tenant/purchase-orders/{$purchaseOrderId}/receive",
            [],
            $this->tenantHeaders()
        )->assertOk();

        $this->getJson("/api/tenant/suppliers/{$supplier->id}/account", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.supplier.current_balance', 140)
            ->assertJsonPath('data.purchase_orders.0.id', $purchaseOrderId)
            ->assertJsonPath('data.purchase_orders.0.remaining_amount', 140)
            ->assertJsonPath('data.purchase_orders.0.expected_delivery_date', '2026-06-05')
            ->assertJsonPath('data.purchase_orders.0.inventory_received', true);
    }

    public function test_tenant_user_can_delete_purchase_order(): void
    {
        $supplier = $this->createSupplier();
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id);
        $this->actingAsTenant($this->purchaseOrderUser);

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

        $this->actingAsTenant($this->purchaseOrderUser);

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
        $this->actingAsTenant($restrictedUser);

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

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'BR-'.uniqid(),
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0:DressCategory,1:DressCategory}
     */
    private function createDressCategoryPair(): array
    {
        $category = DressCategory::query()->create([
            'name' => 'Dresses '.uniqid(),
            'slug' => 'dresses-'.uniqid(),
            'status' => 'active',
        ]);
        $subcategory = DressCategory::query()->create([
            'parent_id' => $category->id,
            'name' => 'Evening '.uniqid(),
            'slug' => 'evening-'.uniqid(),
            'status' => 'active',
        ]);

        return [$category, $subcategory];
    }

    private function createPurchaseOrderViaApi(int $supplierId, float $unitPrice = 100): int
    {
        $this->actingAsTenant($this->purchaseOrderUser);

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

    private function actingAsTenant(User $user): void
    {
        DB::purge('central');
        DB::reconnect('central');

        $tokenResult = $user->createToken('purchase-order-feature-test');
        $tokenResult->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();
        $this->tenantToken = $tokenResult->plainTextToken;
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
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
        ];

        if ($this->tenantToken !== null) {
            $headers['Authorization'] = 'Bearer '.$this->tenantToken;
        }

        return $headers;
    }
}
