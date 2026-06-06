<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSupplierPaymentTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $paymentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->paymentUser = $this->createTenantUserWithPermissions([
            'supplier_payments.view',
            'supplier_payments.create',
            'purchase_orders.create',
        ]);
    }

    public function test_tenant_user_can_add_supplier_payment(): void
    {
        $supplier = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $response = $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 100,
            'method' => 'cash',
            'reference' => 'SP-001',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Supplier payment added')
            ->assertJsonPath('data.amount', '100.00');
    }

    public function test_supplier_payment_updates_purchase_order_paid_remaining_amounts(): void
    {
        $supplier = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 120,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderId,
            'paid_amount' => 120,
            'remaining_amount' => 180,
        ], 'tenant');
    }

    public function test_supplier_payment_updates_purchase_order_status_to_partially_paid(): void
    {
        $supplier = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 100,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderId,
            'status' => PurchaseOrder::STATUS_PARTIALLY_PAID,
        ], 'tenant');
    }

    public function test_supplier_payment_updates_purchase_order_status_to_paid(): void
    {
        $supplier = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 300,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrderId,
            'status' => PurchaseOrder::STATUS_PAID,
            'remaining_amount' => 0,
        ], 'tenant');
    }

    public function test_supplier_payment_creates_cash_movement_out(): void
    {
        $supplier = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 100,
            'method' => 'bank_transfer',
            'reference' => 'TRX-100',
        ], $this->tenantHeaders())->assertCreated();

        $payment = SupplierPayment::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_SUPPLIER_PAYMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 100,
            'method' => 'bank_transfer',
            'reference_type' => CashMovement::REFERENCE_SUPPLIER_PAYMENT,
            'reference_id' => $payment->id,
        ], 'tenant');
    }

    public function test_supplier_payment_deducts_branch_cashbox_and_creates_expense_and_journal_entry(): void
    {
        $supplier = $this->createSupplier(0);
        $branch = Branch::query()->create(['name' => 'Branch A', 'status' => 'active']);
        $cashbox = Cashbox::query()->create([
            'name' => 'Branch A Cashbox',
            'branch_id' => $branch->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 300, $branch->id);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $response = $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 250,
            'method' => 'cash',
            'cashbox_id' => $cashbox->id,
            'reference' => 'SP-CASHBOX-001',
        ], $this->tenantHeaders());
        $response->assertCreated();
        $paymentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('supplier_payments', [
            'id' => $paymentId,
            'branch_id' => $branch->id,
            'cashbox_id' => $cashbox->id,
        ], 'tenant');

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_SUPPLIER_PAYMENT,
            'reference_id' => $paymentId,
            'cashbox_id' => $cashbox->id,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 250,
        ], 'tenant');

        $cashbox->refresh();
        $this->assertSame('750.00', $cashbox->current_balance);

        $this->assertDatabaseHas('expenses', [
            'status' => Expense::STATUS_PAID,
            'branch_id' => $branch->id,
            'cashbox_id' => $cashbox->id,
            'amount' => 250,
            'reference' => 'SP-CASHBOX-001',
        ], 'tenant');

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => JournalEntry::SOURCE_SUPPLIER_PAYMENT,
            'source_id' => $paymentId,
            'branch_id' => $branch->id,
        ], 'tenant');
    }

    public function test_supplier_current_balance_updates_correctly(): void
    {
        $supplier = $this->createSupplier(100);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplier->id, 500);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $supplier->refresh();
        $this->assertSame('600.00', $supplier->current_balance);

        $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 200,
            'method' => 'cash',
        ], $this->tenantHeaders())->assertCreated();

        $supplier->refresh();
        $this->assertSame('400.00', $supplier->current_balance);
    }

    public function test_payment_cannot_be_linked_to_another_supplier_purchase_order(): void
    {
        $supplierA = $this->createSupplier(0);
        $supplierB = $this->createSupplier(0);
        $purchaseOrderId = $this->createPurchaseOrderViaApi($supplierB->id, 250);
        Sanctum::actingAs($this->paymentUser, ['*']);

        $response = $this->postJson("/api/tenant/suppliers/{$supplierA->id}/payments", [
            'purchase_order_id' => $purchaseOrderId,
            'amount' => 50,
            'method' => 'cash',
        ], $this->tenantHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('errors.purchase_order_id.0', 'Purchase order does not belong to the selected supplier');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $supplier = $this->createSupplier(0);
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->postJson("/api/tenant/suppliers/{$supplier->id}/payments", [
            'amount' => 50,
        ], $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createSupplier(float $openingBalance): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Supplier '.uniqid(),
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'status' => 'active',
        ]);
    }

    private function createPurchaseOrderViaApi(int $supplierId, float $total, ?int $branchId = null): int
    {
        Sanctum::actingAs($this->paymentUser, ['*']);

        $payload = [
            'supplier_id' => $supplierId,
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'order_date' => '2026-05-26',
            'items' => [
                ['item_name' => 'Purchase Item', 'quantity' => 1, 'unit_price' => $total],
            ],
            'discount' => 0,
            'tax' => 0,
        ];
        if ($branchId !== null) {
            $payload['branch_id'] = $branchId;
        }

        $response = $this->postJson('/api/tenant/purchase-orders', $payload, $this->tenantHeaders());

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-supplier-payments.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-supplier-payments.sqlite';

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
