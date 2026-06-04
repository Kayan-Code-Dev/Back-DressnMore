<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
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

class TenantInvoicePaymentTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->ownerUser = $this->createTenantUserWithPermissions([
            'invoice_payments.create',
            'invoice_payments.view',
            'invoices.view',
        ]);
    }

    public function test_tenant_user_can_add_payment(): void
    {
        $invoice = $this->createInvoice(total: 100);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 20,
            'method' => 'cash',
            'reference' => 'PAY-001',
            'notes' => 'first payment',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Payment added')
            ->assertJsonPath('data.paid_amount', '20.00');
    }

    public function test_adding_payment_updates_paid_amount_and_remaining_amount(): void
    {
        $invoice = $this->createInvoice(total: 120);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 50,
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('data.paid_amount', '50.00')
            ->assertJsonPath('data.remaining_amount', '70.00');
    }

    public function test_partial_payment_sets_status_partially_paid(): void
    {
        $invoice = $this->createInvoice(total: 100);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 30,
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PARTIALLY_PAID);
    }

    public function test_full_payment_sets_status_paid(): void
    {
        $invoice = $this->createInvoice(total: 100);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 100,
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.remaining_amount', '0.00');
    }

    public function test_payment_cannot_exceed_remaining_amount_after_existing_payment(): void
    {
        $invoice = $this->createInvoice(total: 100);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 80,
        ], $this->tenantHeaders())->assertOk();

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 30,
        ], $this->tenantHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'مبلغ الدفعة يتجاوز المبلغ المتبقي على الفاتورة');

        $invoice->refresh();
        $this->assertSame('80.00', (string) $invoice->paid_amount);
        $this->assertSame('20.00', (string) $invoice->remaining_amount);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_user_without_permission_rejected(): void
    {
        $invoice = $this->createInvoice(total: 100);
        $restrictedUser = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 20,
        ], $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    private function createInvoice(float $total): Invoice
    {
        return Invoice::query()->create([
            'invoice_number' => 'INV-PAY-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
        ]);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-invoice-payments.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoice-payments.sqlite';

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
