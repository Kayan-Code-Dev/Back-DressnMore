<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantTokenBinding;
use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductTransfer;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantProductTransferTest extends TestCase
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
        $this->withoutMiddleware(EnsureTenantTokenBinding::class);
        $this->user = $this->createTenantUserWithPermissions([
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'product_transfers.view',
            'product_transfers.create',
            'product_transfers.confirm',
            'product_transfers.reject',
            'product_transfers.delete',
        ]);
    }

    public function test_product_creation_requires_branch_and_list_filters_by_branch(): void
    {
        $branchA = Branch::query()->create(['name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/tenant/products', [
            'branch_id' => $branchA->id,
            'sku' => 'SKU-001',
            'name' => 'Cotton Fabric',
            'quantity' => 20,
            'sale_price' => 80,
        ], $this->tenantHeaders())
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branchA->id);

        $this->postJson('/api/tenant/products', [
            'branch_id' => $branchB->id,
            'sku' => 'SKU-001',
            'name' => 'Cotton Fabric',
            'quantity' => 10,
            'sale_price' => 80,
        ], $this->tenantHeaders())->assertCreated();

        $this->getJson('/api/tenant/products?branch_id='.$branchA->id, $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.branch_id', $branchA->id);
    }

    public function test_product_transfer_order_and_confirm_updates_stock_and_log(): void
    {
        $fromBranch = Branch::query()->create(['name' => 'Main Branch', 'status' => 'active']);
        $toBranch = Branch::query()->create(['name' => 'Second Branch', 'status' => 'active']);
        $product = Product::query()->create([
            'branch_id' => $fromBranch->id,
            'sku' => 'PRD-10',
            'name' => 'Ready Product',
            'quantity' => 15,
            'sale_price' => 120,
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $createTransfer = $this->postJson('/api/tenant/products/transfers', [
            'product_id' => $product->id,
            'to_branch_id' => $toBranch->id,
            'quantity' => 5,
            'scheduled_delivery_at' => '2026-06-08 11:00:00',
            'notes' => 'Move stock to second branch',
        ], $this->tenantHeaders());
        $createTransfer->assertCreated()
            ->assertJsonPath('data.status', ProductTransfer::STATUS_PENDING)
            ->assertJsonPath('data.product_name', 'Ready Product');
        $transferId = (int) $createTransfer->json('data.id');

        $this->postJson("/api/tenant/products/transfers/{$transferId}/confirm", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', ProductTransfer::STATUS_CONFIRMED)
            ->assertJsonPath('data.requested_by_name', $this->user->name);

        $product->refresh();
        $this->assertSame(10, (int) $product->quantity);

        $targetProduct = Product::query()
            ->where('branch_id', $toBranch->id)
            ->where('sku', 'PRD-10')
            ->firstOrFail();
        $this->assertSame(5, (int) $targetProduct->quantity);

        $this->getJson('/api/tenant/products/transfers?status=confirmed', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_name', 'Ready Product');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-products.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-products.sqlite';

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
