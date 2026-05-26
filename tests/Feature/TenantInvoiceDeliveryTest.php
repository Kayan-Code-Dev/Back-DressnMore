<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\DeliveryRecord;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InventoryMovement;
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

class TenantInvoiceDeliveryTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $deliveryUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->deliveryUser = $this->createTenantUserWithPermissions([
            'invoice_delivery.view',
            'invoice_delivery.deliver',
            'invoice_delivery.return',
        ]);
    }

    public function test_tenant_user_can_deliver_rent_invoice(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [
            'delivered_at' => '2026-05-26 15:00:00',
            'receiver_name' => 'Customer Name',
            'receiver_phone' => '01000000000',
            'notes' => 'Delivered in good condition',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invoice delivered');
    }

    public function test_delivering_rent_invoice_changes_invoice_status_to_delivered(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_DELIVERED);
    }

    public function test_delivering_rent_invoice_changes_dress_status_to_rented(): void
    {
        [$invoice, $dress] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertOk();

        $this->assertDatabaseHas('dresses', [
            'id' => $dress->id,
            'status' => Dress::STATUS_RENTED,
        ], 'tenant');
        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_RENTED,
        ], 'tenant');
    }

    public function test_delivering_sell_invoice_changes_dress_status_to_sold(): void
    {
        [$invoice, $dress] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_SELL, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertOk();

        $this->assertDatabaseHas('dresses', [
            'id' => $dress->id,
            'status' => Dress::STATUS_SOLD,
        ], 'tenant');
        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_SOLD,
        ], 'tenant');
    }

    public function test_delivering_tailoring_invoice_changes_invoice_status_to_delivered(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_TAILORING, Invoice::STATUS_CONFIRMED, false);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_DELIVERED);
    }

    public function test_cannot_deliver_cancelled_invoice(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CANCELLED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Cancelled invoice cannot be delivered');
    }

    public function test_cannot_deliver_invoice_twice(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertOk();

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Invoice is already delivered');
    }

    public function test_tenant_user_can_return_rent_invoice(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $response = $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [
            'returned_at' => '2026-05-30 17:00:00',
            'notes' => 'Returned with minor damage',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invoice returned');
    }

    public function test_returning_rent_invoice_changes_invoice_status_to_returned(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_RETURNED);
    }

    public function test_returning_rent_invoice_changes_dress_status_to_available(): void
    {
        [$invoice, $dress] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertOk();

        $this->assertDatabaseHas('dresses', [
            'id' => $dress->id,
            'status' => Dress::STATUS_AVAILABLE,
        ], 'tenant');
        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_RETURNED,
        ], 'tenant');
    }

    public function test_returning_rent_invoice_can_move_dress_to_maintenance(): void
    {
        [$invoice, $dress] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [
            'dress_status_after_return' => Dress::STATUS_MAINTENANCE,
            'notes' => 'Damage detected',
        ], $this->tenantHeaders())->assertOk();

        $this->assertDatabaseHas('dresses', [
            'id' => $dress->id,
            'status' => Dress::STATUS_MAINTENANCE,
        ], 'tenant');
        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_MAINTENANCE,
        ], 'tenant');
    }

    public function test_cannot_return_non_rent_invoice(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_SELL, Invoice::STATUS_DELIVERED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Only rent invoices can be returned');
    }

    public function test_cannot_return_invoice_before_delivery(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Invoice must be delivered before return');
    }

    public function test_cannot_return_invoice_twice(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertOk();

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Invoice is already returned');
    }

    public function test_delivery_records_can_be_listed(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice();
        Sanctum::actingAs($this->deliveryUser, ['*']);
        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [], $this->tenantHeaders())->assertOk();

        $this->getJson("/api/tenant/invoices/{$invoice->id}/delivery-records", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.type', DeliveryRecord::TYPE_RETURNED);
    }

    public function test_user_without_delivery_permission_is_rejected(): void
    {
        [$invoice] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        $restrictedUser = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    /**
     * @return array{0:Invoice,1:?Dress}
     */
    private function createInvoiceWithOptionalDress(string $type, string $status, bool $withDress = true): array
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DLV-'.uniqid(),
            'type' => $type,
            'status' => $status,
            'total' => 100,
            'remaining_amount' => 100,
        ]);

        $dress = null;
        if ($withDress) {
            $dress = Dress::query()->create([
                'code' => 'DR-DLV-'.uniqid(),
                'name' => 'Delivery Dress',
                'status' => Dress::STATUS_AVAILABLE,
            ]);
        }

        $invoice->items()->create([
            'dress_id' => $dress?->id,
            'description' => 'Invoice item',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        return [$invoice->refresh(), $dress];
    }

    /**
     * @return array{0:Invoice,1:Dress}
     */
    private function createDeliveredRentInvoice(): array
    {
        [$invoice, $dress] = $this->createInvoiceWithOptionalDress(Invoice::TYPE_RENT, Invoice::STATUS_CONFIRMED);
        Sanctum::actingAs($this->deliveryUser, ['*']);
        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())->assertOk();

        return [$invoice->refresh(), $dress];
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-invoice-delivery.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoice-delivery.sqlite';

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
     * @param list<string> $permissionKeys
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
