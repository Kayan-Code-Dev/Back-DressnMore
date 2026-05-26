<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\DressCategory;
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

class TenantDressCategoryTest extends TestCase
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
            'dress_categories.view',
            'dress_categories.create',
            'dress_categories.update',
            'dress_categories.delete',
        ]);
    }

    public function test_tenant_user_can_list_categories(): void
    {
        DressCategory::query()->create(['name' => 'Wedding', 'status' => 'active']);
        DressCategory::query()->create(['name' => 'Evening', 'status' => 'inactive']);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories?search=wedd&status=active', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Wedding');
    }

    public function test_can_create_parent_category(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dress-categories', [
            'name' => 'Bridal',
            'slug' => 'bridal',
            'description' => 'Bridal dresses',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Dress category created')
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.name', 'Bridal');

        $this->assertDatabaseHas('dress_categories', [
            'name' => 'Bridal',
            'slug' => 'bridal',
        ], 'tenant');
    }

    public function test_can_create_subcategory_with_parent_id(): void
    {
        $parent = DressCategory::query()->create([
            'name' => 'Bridal',
            'slug' => 'bridal-parent',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dress-categories', [
            'parent_id' => $parent->id,
            'name' => 'Princess',
            'slug' => 'princess-sub',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.name', 'Princess');
    }

    public function test_tenant_user_can_update_category(): void
    {
        $category = DressCategory::query()->create([
            'name' => 'Old Category',
            'slug' => 'old-category',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/dress-categories/{$category->id}", [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'Updated category',
            'status' => 'inactive',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Dress category updated')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('dress_categories', [
            'id' => $category->id,
            'name' => 'New Category',
            'status' => 'inactive',
        ], 'tenant');
    }

    public function test_tenant_user_can_delete_category(): void
    {
        $category = DressCategory::query()->create([
            'name' => 'Delete Category',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/dress-categories/{$category->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Dress category deleted');

        $this->assertSoftDeleted('dress_categories', ['id' => $category->id], 'tenant');
    }

    public function test_can_filter_only_parents(): void
    {
        $parent = DressCategory::query()->create(['name' => 'Parent', 'status' => 'active']);
        DressCategory::query()->create(['name' => 'Child', 'status' => 'active', 'parent_id' => $parent->id]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories?only_parents=true', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Parent');
    }

    public function test_can_filter_only_children(): void
    {
        $parent = DressCategory::query()->create(['name' => 'Parent Two', 'status' => 'active']);
        DressCategory::query()->create(['name' => 'Child Two', 'status' => 'active', 'parent_id' => $parent->id]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories?only_children=true', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Child Two')
            ->assertJsonPath('data.0.parent_id', $parent->id);
    }

    public function test_can_filter_by_parent_id(): void
    {
        $parent = DressCategory::query()->create(['name' => 'Parent Three', 'status' => 'active']);
        DressCategory::query()->create(['name' => 'Child Three', 'status' => 'active', 'parent_id' => $parent->id]);
        DressCategory::query()->create(['name' => 'Child Other', 'status' => 'active']);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson("/api/tenant/dress-categories?parent_id={$parent->id}", $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Child Three');
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/api/tenant/dress-categories', $this->tenantHeaders());

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_missing_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Tenant workspace is required']);
    }

    public function test_invalid_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories', [
            'Accept' => 'application/json',
            'X-Tenant' => 'invalid-workspace',
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Tenant not found']);
    }

    public function test_user_without_permission_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-dress-categories.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-dress-categories.sqlite';

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
