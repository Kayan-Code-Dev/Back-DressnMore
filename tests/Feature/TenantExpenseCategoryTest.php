<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\ExpenseCategory;
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

class TenantExpenseCategoryTest extends TestCase
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
            'expense_categories.view',
            'expense_categories.create',
            'expense_categories.update',
            'expense_categories.delete',
        ]);
    }

    public function test_tenant_user_can_list_expense_categories(): void
    {
        ExpenseCategory::query()->create(['name' => 'Rent', 'status' => 'active']);
        ExpenseCategory::query()->create(['name' => 'Utilities', 'status' => 'inactive']);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/expense-categories', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_tenant_user_can_create_expense_category(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/expense-categories', [
            'name' => 'Transportation',
            'slug' => 'transportation',
            'description' => 'Transport expenses',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Expense category created')
            ->assertJsonPath('data.name', 'Transportation');

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Transportation',
            'slug' => 'transportation',
        ], 'tenant');
    }

    public function test_tenant_user_can_update_expense_category(): void
    {
        $category = ExpenseCategory::query()->create([
            'name' => 'Old Category',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/expense-categories/{$category->id}", [
            'name' => 'Updated Category',
            'slug' => 'updated-category',
            'description' => 'Updated',
            'status' => 'inactive',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Expense category updated')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'status' => 'inactive',
        ], 'tenant');
    }

    public function test_tenant_user_can_delete_expense_category(): void
    {
        $category = ExpenseCategory::query()->create([
            'name' => 'Delete Category',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/expense-categories/{$category->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Expense category deleted');

        $this->assertSoftDeleted('expense_categories', ['id' => $category->id], 'tenant');
    }

    public function test_search_filter_works(): void
    {
        ExpenseCategory::query()->create(['name' => 'Fabric Supplies', 'status' => 'active']);
        ExpenseCategory::query()->create(['name' => 'Rent', 'status' => 'inactive']);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/expense-categories?search=fabric&status=active', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Fabric Supplies');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/expense-categories', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-expense-categories.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-expense-categories.sqlite';

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
