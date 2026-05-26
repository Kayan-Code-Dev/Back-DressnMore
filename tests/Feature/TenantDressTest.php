<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
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

class TenantDressTest extends TestCase
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
            'dresses.view',
            'dresses.create',
            'dresses.update',
            'dresses.delete',
            'inventory.view',
            'inventory.manage',
        ]);
    }

    public function test_tenant_user_can_list_dresses(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal', 'status' => 'active']);
        Dress::query()->create([
            'dress_category_id' => $category->id,
            'code' => 'DR-001',
            'name' => 'Classic White',
            'color' => 'White',
            'size' => 'M',
            'status' => 'available',
        ]);
        Dress::query()->create([
            'code' => 'DR-002',
            'name' => 'Red Dress',
            'color' => 'Red',
            'size' => 'S',
            'status' => 'maintenance',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses?search=classic', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'DR-001');
    }

    public function test_tenant_user_can_create_dress(): void
    {
        $category = DressCategory::query()->create(['name' => 'Evening', 'status' => 'active']);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dresses', [
            'dress_category_id' => $category->id,
            'code' => 'DR-CREATE-01',
            'name' => 'Create Dress',
            'description' => 'Dress description',
            'size' => 'L',
            'color' => 'Blue',
            'purchase_price' => 100.50,
            'rental_price' => 40.25,
            'sale_price' => 180.75,
            'status' => 'available',
            'notes' => 'created from test',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Dress created')
            ->assertJsonPath('data.code', 'DR-CREATE-01')
            ->assertJsonPath('data.display_name', 'DR-CREATE-01 - Evening');

        $this->assertDatabaseHas('dresses', [
            'code' => 'DR-CREATE-01',
            'name' => 'Create Dress',
        ], 'tenant');
    }

    public function test_creating_dress_creates_inventory_movement(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dresses', [
            'code' => 'DR-MOVE-01',
            'name' => 'Movement Dress',
            'status' => 'available',
        ], $this->tenantHeaders());

        $response->assertCreated();
        $dressId = (int) $response->json('data.id');

        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dressId,
            'type' => InventoryMovement::TYPE_CREATED,
        ], 'tenant');
    }

    public function test_tenant_user_can_update_dress(): void
    {
        $dress = Dress::query()->create([
            'code' => 'DR-UPDATE-01',
            'name' => 'Old Dress Name',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/dresses/{$dress->id}", [
            'code' => 'DR-UPDATE-01',
            'name' => 'New Dress Name',
            'description' => 'Updated description',
            'size' => 'XL',
            'color' => 'Green',
            'purchase_price' => 120,
            'rental_price' => 55,
            'sale_price' => 210,
            'status' => 'available',
            'notes' => 'updated note',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Dress updated')
            ->assertJsonPath('data.name', 'New Dress Name')
            ->assertJsonPath('data.color', 'Green');

        $this->assertDatabaseHas('dresses', [
            'id' => $dress->id,
            'name' => 'New Dress Name',
            'color' => 'Green',
        ], 'tenant');
    }

    public function test_changing_status_creates_inventory_movement(): void
    {
        $dress = Dress::query()->create([
            'code' => 'DR-STATUS-01',
            'name' => 'Status Dress',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/dresses/{$dress->id}", [
            'code' => 'DR-STATUS-01',
            'name' => 'Status Dress',
            'status' => 'maintenance',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', 'maintenance');

        $this->assertDatabaseHas('inventory_movements', [
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_STATUS_CHANGED,
        ], 'tenant');
    }

    public function test_tenant_user_can_delete_dress(): void
    {
        $dress = Dress::query()->create([
            'code' => 'DR-DELETE-01',
            'name' => 'Delete Dress',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/dresses/{$dress->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Dress deleted');

        $this->assertSoftDeleted('dresses', ['id' => $dress->id], 'tenant');
    }

    public function test_search_filter_works(): void
    {
        $bridal = DressCategory::query()->create(['name' => 'Bridal', 'status' => 'active']);
        $evening = DressCategory::query()->create(['name' => 'Evening', 'status' => 'active']);

        Dress::query()->create([
            'dress_category_id' => $bridal->id,
            'code' => 'DR-FILTER-RED',
            'name' => 'Red Bridal Dress',
            'color' => 'Red',
            'size' => 'M',
            'status' => 'available',
        ]);
        Dress::query()->create([
            'dress_category_id' => $bridal->id,
            'code' => 'DR-FILTER-BLUE',
            'name' => 'Blue Bridal Dress',
            'color' => 'Blue',
            'size' => 'M',
            'status' => 'available',
        ]);
        Dress::query()->create([
            'dress_category_id' => $evening->id,
            'code' => 'DR-FILTER-RED-2',
            'name' => 'Red Evening Dress',
            'color' => 'Red',
            'size' => 'S',
            'status' => 'maintenance',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson(
            "/api/tenant/dresses?search=red&dress_category_id={$bridal->id}&status=available&color=Red&size=M",
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'DR-FILTER-RED');
    }

    public function test_dress_resource_returns_display_name_as_code_category_subcategory(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Princess',
            'status' => 'active',
            'parent_id' => $category->id,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dresses', [
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'code' => 'DR-DISPLAY-01',
            'name' => 'Display Dress',
            'status' => 'available',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.display_name', 'DR-DISPLAY-01 - Bridal - Princess');
    }

    public function test_dress_listing_returns_code_category_subcategory_separately(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Classic',
            'status' => 'active',
            'parent_id' => $category->id,
        ]);
        Dress::query()->create([
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'code' => 'DR-LIST-01',
            'name' => 'List Dress',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses?search=DR-LIST-01', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'DR-LIST-01')
            ->assertJsonPath('data.0.category.name', 'Bridal')
            ->assertJsonPath('data.0.subcategory.name', 'Classic');
    }

    public function test_search_works_by_category_name(): void
    {
        $category = DressCategory::query()->create(['name' => 'Bridal Search', 'status' => 'active']);
        Dress::query()->create([
            'dress_category_id' => $category->id,
            'code' => 'DR-CAT-SRCH',
            'name' => 'Search Dress',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses?search=bridal search', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'DR-CAT-SRCH');
    }

    public function test_search_works_by_subcategory_name(): void
    {
        $category = DressCategory::query()->create(['name' => 'Main Cat', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Princess Search',
            'status' => 'active',
            'parent_id' => $category->id,
        ]);
        Dress::query()->create([
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'code' => 'DR-SUB-SRCH',
            'name' => 'Sub Search Dress',
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses?search=princess search', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'DR-SUB-SRCH');
    }

    public function test_can_create_use_branch_in_test_setup(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'BR-MAIN',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Main Branch',
        ], 'tenant');
    }

    public function test_dress_can_belong_to_branch(): void
    {
        $branch = Branch::query()->create(['name' => 'Downtown', 'status' => 'active']);
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/dresses', [
            'code' => 'DR-BR-01',
            'name' => 'Branch Dress',
            'branch_id' => $branch->id,
            'status' => 'available',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.branch.name', 'Downtown');
    }

    public function test_dress_list_can_filter_by_branch_id(): void
    {
        $branchA = Branch::query()->create(['name' => 'A', 'status' => 'active']);
        $branchB = Branch::query()->create(['name' => 'B', 'status' => 'active']);

        Dress::query()->create([
            'code' => 'DR-BR-A',
            'name' => 'Branch A Dress',
            'branch_id' => $branchA->id,
            'status' => 'available',
        ]);
        Dress::query()->create([
            'code' => 'DR-BR-B',
            'name' => 'Branch B Dress',
            'branch_id' => $branchB->id,
            'status' => 'available',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson("/api/tenant/dresses?branch_id={$branchA->id}", $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'DR-BR-A');
    }

    public function test_inventory_movement_supports_from_branch_id_and_to_branch_id(): void
    {
        $branchA = Branch::query()->create(['name' => 'From Branch', 'status' => 'active']);
        $branchB = Branch::query()->create(['name' => 'To Branch', 'status' => 'active']);
        $dress = Dress::query()->create([
            'code' => 'DR-MV-BR',
            'name' => 'Movement Branch Dress',
            'status' => 'available',
        ]);

        InventoryMovement::query()->create([
            'dress_id' => $dress->id,
            'type' => InventoryMovement::TYPE_BRANCH_TRANSFER,
            'quantity' => 1,
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson("/api/tenant/dresses/{$dress->id}/inventory-movements", $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.from_branch_id', $branchA->id)
            ->assertJsonPath('data.0.to_branch_id', $branchB->id);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/api/tenant/dresses', $this->tenantHeaders());

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_missing_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Tenant workspace is required']);
    }

    public function test_invalid_tenant_rejected(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/dresses', [
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

        $response = $this->getJson('/api/tenant/dresses', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-dresses.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-dresses.sqlite';

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
