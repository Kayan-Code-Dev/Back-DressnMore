<?php

namespace Tests\Feature;

use App\Enums\SecurityDepositStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSecurityDepositTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $securityDepositUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->securityDepositUser = $this->createTenantUserWithPermissions([
            'security_deposit.view',
            'security_deposit.deduct',
        ]);
    }

    public function test_tenant_user_can_add_security_deposit_deduction(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(500);
        Sanctum::actingAs($this->securityDepositUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 200,
            'reason' => 'Minor damage repair',
            'notes' => 'Small tear on sleeve',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Security deposit deduction added');

        $this->assertDatabaseHas('security_deposit_transactions', [
            'invoice_id' => $invoice->id,
            'type' => SecurityDepositTransaction::TYPE_DEDUCTED,
            'amount' => 200,
        ], 'tenant');
    }

    public function test_deduction_cannot_exceed_security_deposit_balance(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(100);
        Sanctum::actingAs($this->securityDepositUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 150,
            'reason' => 'Major damage',
        ], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Deduction amount exceeds remaining security deposit balance');
    }

    public function test_deposit_transaction_can_be_listed(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(500);
        Sanctum::actingAs($this->securityDepositUser, ['*']);
        $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 50,
            'reason' => 'Minor damage',
        ], $this->tenantHeaders())->assertOk();

        $this->getJson("/api/tenant/invoices/{$invoice->id}/security-deposit/transactions", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', SecurityDepositTransaction::TYPE_DEDUCTED);
    }

    public function test_deposit_status_updates_to_partially_deducted(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(500);
        Sanctum::actingAs($this->securityDepositUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 200,
            'reason' => 'Damage',
        ], $this->tenantHeaders())->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'security_deposit_status' => SecurityDepositStatus::PARTIALLY_DEDUCTED->value,
        ], 'tenant');
    }

    public function test_deposit_status_updates_to_fully_deducted(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(500);
        Sanctum::actingAs($this->securityDepositUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 500,
            'reason' => 'Heavy repair',
        ], $this->tenantHeaders())->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'security_deposit_status' => SecurityDepositStatus::FULLY_DEDUCTED->value,
        ], 'tenant');
        $this->assertDatabaseHas('dresses', [
            'status' => Dress::STATUS_MAINTENANCE,
        ], 'tenant');
    }

    public function test_user_without_security_deposit_permission_is_rejected(): void
    {
        $invoice = $this->createRentInvoiceWithDeposit(500);
        $restrictedUser = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/security-deposit/deductions", [
            'amount' => 100,
        ], $this->tenantHeaders())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createRentInvoiceWithDeposit(float $deposit): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DEP-'.uniqid(),
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_DELIVERED,
            'total' => 300,
            'remaining_amount' => 300,
            'security_deposit' => $deposit,
            'security_deposit_status' => SecurityDepositStatus::NONE->value,
        ]);

        $dress = Dress::query()->create([
            'code' => 'DR-DEP-'.uniqid(),
            'name' => 'Deposit Dress',
            'status' => Dress::STATUS_AVAILABLE,
        ]);

        $invoice->items()->create([
            'dress_id' => $dress->id,
            'description' => 'Rent item',
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

        $this->centralDatabasePath = $testingPath.'/central-security-deposit.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-security-deposit.sqlite';

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
