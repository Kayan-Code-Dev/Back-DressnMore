<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
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

class TenantCustomerTest extends TestCase
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
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
        ]);
    }

    public function test_tenant_user_can_list_customers(): void
    {
        Customer::query()->create([
            'name' => 'Alice Tailor',
            'phone' => '12345',
            'status' => 'active',
        ]);

        Customer::query()->create([
            'name' => 'Mona Atelier',
            'phone' => '99999',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/customers?search=alice', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alice Tailor');
    }

    public function test_tenant_user_can_create_customer(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/customers', [
            'name' => 'Nour Customer',
            'phone' => '2011000000',
            'whatsapp' => '2011000000',
            'email' => 'nour@example.com',
            'address' => 'Main Street',
            'national_id' => 'A1234',
            'notes' => 'VIP customer',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Customer created')
            ->assertJsonPath('data.name', 'Nour Customer');

        $this->assertDatabaseHas('customers', [
            'name' => 'Nour Customer',
            'email' => 'nour@example.com',
        ], 'tenant');
    }

    public function test_tenant_user_can_update_customer(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Old Name',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/customers/{$customer->id}", [
            'name' => 'Updated Name',
            'phone' => '777',
            'whatsapp' => '888',
            'email' => 'updated@example.com',
            'address' => 'Updated Address',
            'national_id' => 'NID-22',
            'notes' => 'updated notes',
            'status' => 'inactive',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Customer updated')
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'status' => 'inactive',
        ], 'tenant');
    }

    public function test_tenant_user_can_delete_customer(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Delete Me',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/customers/{$customer->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Customer deleted');

        $this->assertSoftDeleted('customers', ['id' => $customer->id], 'tenant');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/tenant/customers', $this->tenantHeaders());

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);
    }

    public function test_missing_tenant_is_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/customers', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Tenant context is required',
            ]);
    }

    public function test_invalid_tenant_is_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/customers', [
            'Accept' => 'application/json',
            'X-Tenant' => 'invalid-workspace',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Tenant not found',
            ]);
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/customers', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');

        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-customers.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-customers.sqlite';

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
