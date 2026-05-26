<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSupplierTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $supplierUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->supplierUser = $this->createTenantUserWithPermissions([
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
        ]);
    }

    public function test_tenant_user_can_list_suppliers(): void
    {
        Supplier::query()->create(['name' => 'Supplier One', 'status' => 'active']);
        Supplier::query()->create(['name' => 'Supplier Two', 'status' => 'inactive']);

        Sanctum::actingAs($this->supplierUser, ['*']);

        $response = $this->getJson('/api/tenant/suppliers', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_tenant_user_can_create_supplier(): void
    {
        Sanctum::actingAs($this->supplierUser, ['*']);

        $response = $this->postJson('/api/tenant/suppliers', [
            'name' => 'Modern Fabrics',
            'phone' => '01000000000',
            'whatsapp' => '01000000000',
            'email' => 'supplier@example.com',
            'address' => 'Cairo',
            'tax_number' => 'TX-1234',
            'opening_balance' => 150,
            'notes' => 'Preferred supplier',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Supplier created')
            ->assertJsonPath('data.name', 'Modern Fabrics')
            ->assertJsonPath('data.current_balance', '150.00');
    }

    public function test_tenant_user_can_update_supplier(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Old Supplier',
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->supplierUser, ['*']);

        $response = $this->putJson("/api/tenant/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier',
            'phone' => '01111111111',
            'whatsapp' => '01111111111',
            'email' => 'updated@supplier.com',
            'address' => 'Giza',
            'tax_number' => 'TX-999',
            'opening_balance' => 50,
            'notes' => 'Updated notes',
            'status' => 'inactive',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Supplier updated')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.current_balance', '50.00');
    }

    public function test_tenant_user_can_delete_supplier(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Delete Supplier',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->supplierUser, ['*']);

        $response = $this->deleteJson("/api/tenant/suppliers/{$supplier->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Supplier deleted');

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id], 'tenant');
    }

    public function test_search_filter_works(): void
    {
        Supplier::query()->create([
            'name' => 'Luxury Fabrics',
            'phone' => '0101',
            'status' => 'active',
        ]);
        Supplier::query()->create([
            'name' => 'Buttons House',
            'phone' => '0202',
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($this->supplierUser, ['*']);

        $response = $this->getJson('/api/tenant/suppliers?search=luxury&status=active', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Luxury Fabrics');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/suppliers', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-suppliers.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-suppliers.sqlite';

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
