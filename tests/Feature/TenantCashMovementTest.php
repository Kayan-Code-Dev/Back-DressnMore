<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Dress;
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

class TenantCashMovementTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $cashUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->cashUser = $this->createTenantUserWithPermissions([
            'cash_movements.view',
            'cash_movements.create',
            'invoice_payments.create',
            'security_deposit.deduct',
        ]);
    }

    public function test_tenant_user_can_list_cash_movements(): void
    {
        CashMovement::query()->create([
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 1000,
            'method' => 'cash',
            'movement_date' => '2026-05-26 18:00:00',
            'description' => 'Opening cash balance',
        ]);

        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->getJson('/api/tenant/cash-movements', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tenant_user_can_create_manual_cash_movement_in(): void
    {
        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->postJson('/api/tenant/cash-movements', [
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 1000,
            'method' => 'cash',
            'movement_date' => '2026-05-26 18:00:00',
            'description' => 'Opening cash balance',
            'notes' => 'Initial cash setup',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Cash movement created')
            ->assertJsonPath('data.direction', 'in');
    }

    public function test_tenant_user_can_create_manual_cash_movement_out(): void
    {
        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->postJson('/api/tenant/cash-movements', [
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 200,
            'method' => 'cash',
            'movement_date' => '2026-05-26 19:00:00',
            'description' => 'Petty cash adjustment',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.amount', '200.00');
    }

    public function test_invalid_direction_is_rejected(): void
    {
        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->postJson('/api/tenant/cash-movements', [
            'type' => CashMovement::TYPE_INCOME,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 100,
            'method' => 'cash',
        ], $this->tenantHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('errors.direction.0', 'Income cash movement direction must be in');
    }

    public function test_invoice_payment_creates_cash_movement_in(): void
    {
        $invoice = $this->createInvoiceForPayment();
        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 250,
            'method' => 'cash',
            'reference' => 'PAY-CM-01',
            'paid_at' => '2026-05-26 20:00:00',
        ], $this->tenantHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_INVOICE_PAYMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 250,
            'method' => 'cash',
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
        ], 'tenant');
    }

    public function test_security_deposit_deduction_creates_cash_movement_in(): void
    {
        $invoice = $this->createInvoiceForSecurityDeposit();
        Sanctum::actingAs($this->cashUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 120,
            'reason' => 'Minor damage repair',
            'notes' => 'Sleeve damage',
        ], $this->tenantHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_DEDUCTION,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 120,
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
        ], 'tenant');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/cash-movements', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createInvoiceForPayment(): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => 'INV-CM-PAY-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
        ]);
    }

    private function createInvoiceForSecurityDeposit(): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-CM-DEP-'.uniqid(),
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'subtotal' => 300,
            'total' => 300,
            'paid_amount' => 0,
            'remaining_amount' => 300,
            'security_deposit' => 300,
            'security_deposit_status' => 'none',
        ]);

        $dress = Dress::query()->create([
            'code' => 'DR-CM-'.uniqid(),
            'name' => 'Cash Movement Dress',
            'status' => Dress::STATUS_AVAILABLE,
        ]);

        $invoice->items()->create([
            'dress_id' => $dress->id,
            'description' => 'Rent dress item',
            'quantity' => 1,
            'unit_price' => 300,
            'total' => 300,
        ]);

        return $invoice;
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-cash-movements.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-cash-movements.sqlite';

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
