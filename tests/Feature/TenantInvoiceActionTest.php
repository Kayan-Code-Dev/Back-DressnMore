<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
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

class TenantInvoiceActionTest extends TestCase
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
            'invoices.view',
            'invoices.create',
            'invoices.cancel',
            'invoices.export',
        ]);
    }

    public function test_invoice_create_with_ui_fields_and_filters(): void
    {
        $customer = Customer::query()->create(['name' => 'Invoice Customer', 'status' => 'active']);
        $branch = Branch::query()->create(['name' => 'Invoice Branch', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $create = $this->postJson('/api/tenant/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'visit_datetime' => '2026-06-01 10:00:00',
            'occasion_datetime' => '2026-06-03 18:00:00',
            'days_of_rent' => 3,
            'delivery_date' => '2026-06-02',
            'order_notes' => 'UI order notes',
            'notes' => 'base note',
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $this->tenantHeaders());

        $create->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.discount_type', 'percentage')
            ->assertJsonPath('data.discount_value', '10.00')
            ->assertJsonPath('data.order_notes', 'UI order notes');

        $this->getJson("/api/tenant/invoices?customer_id={$customer->id}&branch_id={$branch->id}&type=sell&status=confirmed", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_invoice_cancel_and_export_endpoints(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-CANCEL-UI',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Invoice cancelled')
            ->assertJsonPath('data.status', 'cancelled');

        $response = $this->get('/api/tenant/invoices/export', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('invoices.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('INV-CANCEL-UI', $response->streamedContent());
    }

    public function test_partially_paid_invoice_cannot_be_cancelled(): void
    {
        $invoice = $this->createSellInvoice(total: 100, paidAmount: 40, remainingAmount: 60, status: Invoice::STATUS_PARTIALLY_PAID);
        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 40,
            'status' => InvoicePayment::STATUS_PAID,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
            'paid_at' => now(),
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice'])
            ->assertJsonPath(
                'errors.invoice.0',
                'Cannot cancel invoice with recorded payments. Cancel or reverse payments first.',
            );

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->refresh()->status);
    }

    public function test_fully_paid_invoice_cannot_be_cancelled(): void
    {
        $invoice = $this->createSellInvoice(total: 100, paidAmount: 100, remainingAmount: 0, status: Invoice::STATUS_PAID);
        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'status' => InvoicePayment::STATUS_PAID,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
            'paid_at' => now(),
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
    }

    public function test_rent_invoice_with_paid_payment_cannot_be_cancelled(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-RENT-PAY-'.uniqid(),
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'total' => 200,
            'paid_amount' => 50,
            'remaining_amount' => 150,
        ]);
        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => InvoicePayment::STATUS_PAID,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
            'paid_at' => now(),
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-CANCELLED-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CANCELLED,
            'total' => 100,
            'paid_amount' => 0,
            'remaining_amount' => 100,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice'])
            ->assertJsonPath('errors.invoice.0', 'Invoice already cancelled');
    }

    public function test_delivered_invoice_cannot_be_cancelled(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DELIVERED-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_DELIVERED,
            'total' => 100,
            'paid_amount' => 0,
            'remaining_amount' => 100,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice'])
            ->assertJsonPath('errors.invoice.0', 'Delivered or returned invoices cannot be cancelled');
    }

    public function test_returned_invoice_cannot_be_cancelled(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-RETURNED-'.uniqid(),
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_RETURNED,
            'total' => 100,
            'paid_amount' => 0,
            'remaining_amount' => 0,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice'])
            ->assertJsonPath('errors.invoice.0', 'Delivered or returned invoices cannot be cancelled');
    }

    public function test_cancelled_invoice_rejects_new_payment(): void
    {
        $user = $this->createTenantUserWithPermissions([
            'invoices.view',
            'invoices.cancel',
            'invoice_payments.create',
        ]);
        $invoice = $this->createSellInvoice(total: 100, paidAmount: 0, remainingAmount: 100, status: Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/cancel", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/tenant/invoices/{$invoice->id}/payments", [
            'amount' => 10,
            'method' => 'cash',
        ], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice'])
            ->assertJsonPath('errors.invoice.0', 'Cannot add payment to a cancelled invoice');
    }

    private function createSellInvoice(
        float $total,
        float $paidAmount,
        float $remainingAmount,
        string $status,
    ): Invoice {
        return Invoice::query()->create([
            'invoice_number' => 'INV-SELL-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => $status,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
        ]);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-invoice-actions.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoice-actions.sqlite';
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
