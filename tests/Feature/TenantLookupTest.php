<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
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

class TenantLookupTest extends TestCase
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
        $this->user = $this->createTenantUserWithPermissions(['dashboard.view']);
    }

    public function test_tenant_user_can_get_lookups(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/lookups', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_types.0.value', 'rent')
            ->assertJsonPath('data.invoice_statuses.0.value', 'draft')
            ->assertJsonPath('data.payment_methods.0.value', 'cash')
            ->assertJsonPath('data.inventory_movement_types.0.value', 'created')
            ->assertJsonPath('data.delivery_record_types.0.value', 'delivered')
            ->assertJsonPath('data.security_deposit_transaction_types.0.value', 'collected')
            ->assertJsonPath('data.expense_statuses.0.value', 'active')
            ->assertJsonPath('data.supplier_statuses.0.value', 'active')
            ->assertJsonPath('data.purchase_order_statuses.0.value', 'draft')
            ->assertJsonPath('data.cash_movement_types.0.value', 'income')
            ->assertJsonPath('data.cash_movement_directions.0.value', 'in')
            ->assertJsonPath('data.dress_status_after_return.0.value', 'available')
            ->assertJsonFragment(['value' => 'supplier_payment']);
    }

    public function test_lookup_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/tenant/lookups', $this->tenantHeaders());

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_missing_tenant_rejected(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/lookups', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tenant workspace is required');
    }

    public function test_invalid_tenant_rejected(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/lookups', [
            'Accept' => 'application/json',
            'X-Tenant' => 'invalid-workspace',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tenant not found');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-lookups.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-lookups.sqlite';

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
