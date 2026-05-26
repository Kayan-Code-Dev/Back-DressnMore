<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
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

class TenantPaymentStandaloneTest extends TestCase
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
            'payments.view',
            'payments.pay',
            'payments.cancel',
            'payments.export',
        ]);
    }

    public function test_payments_list_show_pay_cancel_export_flow(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-PAY-STANDALONE',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 200,
            'remaining_amount' => 200,
        ]);

        $pendingPayment = InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => InvoicePayment::STATUS_PENDING,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
        ]);

        $paidPayment = InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 60,
            'status' => InvoicePayment::STATUS_PAID,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
            'paid_at' => '2026-05-26 12:00:00',
        ]);
        CashMovement::query()->create([
            'type' => CashMovement::TYPE_INVOICE_PAYMENT,
            'amount' => 60,
            'direction' => CashMovement::DIRECTION_IN,
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $paidPayment->id,
            'movement_date' => '2026-05-26 12:00:00',
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/tenant/payments?status=pending', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $pendingPayment->id);

        $this->getJson("/api/tenant/payments/{$pendingPayment->id}", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->postJson("/api/tenant/payments/{$pendingPayment->id}/pay", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $pendingPayment->id,
            'direction' => 'in',
        ], 'tenant');

        $this->postJson("/api/tenant/payments/{$paidPayment->id}/cancel", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $paidPayment->id,
            'is_reversed' => 1,
        ], 'tenant');

        $response = $this->get('/api/tenant/payments/export', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('payments.csv', (string) $response->headers->get('content-disposition'));
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-payments-standalone.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-payments-standalone.sqlite';
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
