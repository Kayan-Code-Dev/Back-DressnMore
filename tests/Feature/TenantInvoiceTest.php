<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
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

class TenantInvoiceTest extends TestCase
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
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
        ]);
    }

    public function test_tenant_user_can_list_invoices(): void
    {
        Invoice::query()->create([
            'invoice_number' => 'INV-LIST-1',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_DRAFT,
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        Invoice::query()->create([
            'invoice_number' => 'INV-LIST-2',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 200,
            'remaining_amount' => 200,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/invoices', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_tenant_user_can_create_sell_invoice(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Sell Customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'items' => [
                ['description' => 'Sell Item', 'quantity' => 2, 'unit_price' => 50],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Invoice created')
            ->assertJsonPath('data.type', Invoice::TYPE_SELL);
    }

    public function test_tenant_user_can_create_rent_invoice(): void
    {
        $dress = $this->createDress();
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-10',
            'items' => [
                ['dress_id' => $dress->id, 'description' => 'Rent item', 'quantity' => 1, 'unit_price' => 80],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.type', Invoice::TYPE_RENT)
            ->assertJsonPath('data.rent_start_date', '2026-06-01');
    }

    public function test_tenant_user_can_create_tailoring_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_TAILORING,
            'status' => Invoice::STATUS_CONFIRMED,
            'tailoring_due_date' => '2026-07-15',
            'tailoring_notes' => 'Add custom sleeves',
            'items' => [
                ['item_type' => 'tailoring_service', 'description' => 'Tailoring work', 'quantity' => 1, 'unit_price' => 120],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.type', Invoice::TYPE_TAILORING)
            ->assertJsonPath('data.tailoring_due_date', '2026-07-15');
    }

    public function test_invoice_totals_are_calculated_correctly(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'discount' => 10,
            'tax' => 5,
            'items' => [
                ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 50], // 100
                ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 20], // 20
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '120.00')
            ->assertJsonPath('data.discount', '10.00')
            ->assertJsonPath('data.tax', '5.00')
            ->assertJsonPath('data.total', '115.00')
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.remaining_amount', '115.00');
    }

    public function test_tenant_user_can_update_invoice(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-UPD-1',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'subtotal' => 50,
            'total' => 50,
            'remaining_amount' => 50,
        ]);
        $invoice->items()->create([
            'description' => 'Old item',
            'quantity' => 1,
            'unit_price' => 50,
            'total' => 50,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/invoices/{$invoice->id}", [
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'discount' => 5,
            'tax' => 0,
            'items' => [
                ['description' => 'Updated item', 'quantity' => 2, 'unit_price' => 30], // 60
            ],
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Invoice updated')
            ->assertJsonPath('data.subtotal', '60.00')
            ->assertJsonPath('data.total', '55.00');
    }

    public function test_tenant_user_can_delete_invoice(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DEL-1',
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_DRAFT,
            'total' => 10,
            'remaining_amount' => 10,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/invoices/{$invoice->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Invoice deleted');

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id], 'tenant');
    }

    public function test_search_filter_works(): void
    {
        $customerA = Customer::query()->create(['name' => 'Customer A', 'status' => 'active']);
        $customerB = Customer::query()->create(['name' => 'Customer B', 'status' => 'active']);

        $invoiceA = Invoice::query()->create([
            'invoice_number' => 'INV-FLT-ALPHA',
            'customer_id' => $customerA->id,
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        $invoiceA->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        $invoiceB = Invoice::query()->create([
            'invoice_number' => 'INV-FLT-BETA',
            'customer_id' => $customerB->id,
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_PAID,
            'total' => 100,
            'paid_amount' => 100,
            'remaining_amount' => 0,
        ]);
        $invoiceB->forceFill(['created_at' => '2026-09-01 10:00:00'])->save();

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson(
            "/api/tenant/invoices?search=alpha&customer_id={$customerA->id}&type=sell&status=confirmed&date_from=2026-08-01&date_to=2026-08-31",
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-FLT-ALPHA');
    }

    public function test_invoice_item_returns_dress_display_name(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Princess',
            'status' => 'active',
            'parent_id' => $category->id,
        ]);
        $dress = Dress::query()->create([
            'code' => 'DR-ITEM-01',
            'name' => 'Invoice Item Dress',
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $create = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $this->tenantHeaders());

        $invoiceId = (int) $create->json('data.id');
        $show = $this->getJson("/api/tenant/invoices/{$invoiceId}", $this->tenantHeaders());

        $show->assertOk()
            ->assertJsonPath('data.items.0.dress_display_name', 'DR-ITEM-01-Bridal-Princess');
    }

    public function test_invoice_item_returns_dress_code_category_subcategory(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal 2', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Mermaid',
            'status' => 'active',
            'parent_id' => $category->id,
        ]);
        $dress = Dress::query()->create([
            'code' => 'DR-ITEM-02',
            'name' => 'Invoice Item Dress 2',
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $create = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 120],
            ],
        ], $this->tenantHeaders());

        $invoiceId = (int) $create->json('data.id');
        $show = $this->getJson("/api/tenant/invoices/{$invoiceId}", $this->tenantHeaders());

        $show->assertOk()
            ->assertJsonPath('data.items.0.dress_code', 'DR-ITEM-02')
            ->assertJsonPath('data.items.0.dress_category', 'Bridal 2')
            ->assertJsonPath('data.items.0.dress_subcategory', 'Mermaid');
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/api/tenant/invoices', $this->tenantHeaders());

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_missing_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/invoices', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Tenant workspace is required']);
    }

    public function test_invalid_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/invoices', [
            'Accept' => 'application/json',
            'X-Tenant' => 'invalid-workspace',
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Tenant not found']);
    }

    public function test_user_without_permission_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['customers.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/invoices', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    public function test_cannot_rent_same_dress_in_overlapping_dates(): void
    {
        $dress = $this->createDress();

        $existing = Invoice::query()->create([
            'invoice_number' => 'INV-RENT-EXIST',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-10',
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        $existing->items()->create([
            'dress_id' => $dress->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-05',
            'rent_end_date' => '2026-06-12',
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 80],
            ],
        ], $this->tenantHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('errors.rent_period.0', 'الفستان غير متاح خلال فترة التأجير المحددة.');
    }

    public function test_can_rent_same_dress_in_non_overlapping_dates(): void
    {
        $dress = $this->createDress();

        $existing = Invoice::query()->create([
            'invoice_number' => 'INV-RENT-NONOVERLAP',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-10',
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        $existing->items()->create([
            'dress_id' => $dress->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-11',
            'rent_end_date' => '2026-06-15',
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 90],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.type', Invoice::TYPE_RENT);
    }

    public function test_cancelled_invoice_does_not_block_availability(): void
    {
        $dress = $this->createDress();

        $existing = Invoice::query()->create([
            'invoice_number' => 'INV-RENT-CANCELLED',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CANCELLED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-10',
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        $existing->items()->create([
            'dress_id' => $dress->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-05',
            'rent_end_date' => '2026-06-12',
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 70],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.type', Invoice::TYPE_RENT);
    }

    private function createDress(): Dress
    {
        return Dress::query()->create([
            'code' => 'DR-'.uniqid(),
            'name' => 'Dress '.uniqid(),
            'status' => 'available',
        ]);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-invoices.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoices.sqlite';

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
