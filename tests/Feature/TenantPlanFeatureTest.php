<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Models\Central\Tenant;
use App\Models\Tenant\DressCategory;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Support\PlanFeatureCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantPlanFeatureTest extends TestCase
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
        $this->tenant = $this->createTenantWithPlan([
            'categories.enabled' => 'true',
            'subcategories.enabled' => 'false',
            'customers.enabled' => 'false',
        ]);
        $this->user = $this->createTenantUserWithPermissions([
            'dress_categories.view',
            'dress_categories.create',
            'customers.view',
        ]);
    }

    public function test_categories_enabled_allows_parent_category_list(): void
    {
        DressCategory::query()->create(['name' => 'Parent', 'status' => 'active']);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/dress-categories?only_parents=1', $this->tenantHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_subcategories_disabled_blocks_subcategory_create(): void
    {
        $parent = DressCategory::query()->create(['name' => 'Parent', 'status' => 'active']);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/dress-categories', [
            'parent_id' => $parent->id,
            'name' => 'Child',
            'status' => 'active',
        ], $this->tenantHeaders());

        $response->assertForbidden()
            ->assertJsonPath('message', 'Feature is not available');
    }

    public function test_disabled_customer_module_returns_forbidden(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/customers', $this->tenantHeaders());

        $response->assertForbidden()
            ->assertJsonPath('message', 'Feature is not available');
    }

    public function test_me_includes_enabled_modules(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/me', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['categories'])
            ->assertJsonMissing(['subcategories']);
    }

    private function prepareSqliteDatabases(): void
    {
        $this->centralDatabasePath = database_path('testing-central-plan-features.sqlite');
        $this->tenantDatabasePath = database_path('testing-tenant-plan-features.sqlite');

        foreach ([$this->centralDatabasePath, $this->tenantDatabasePath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
            touch($path);
        }

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

        Config::set('database.default', 'central');
        DB::purge('central');
        DB::purge('tenant');
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate', [
            '--database' => 'central',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
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

    /**
     * @param  array<string, string>  $featureOverrides
     */
    private function createTenantWithPlan(array $featureOverrides): Tenant
    {
        $plan = Plan::query()->create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.uniqid(),
            'price' => 0,
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);

        foreach (PlanFeatureCatalog::keys() as $featureKey) {
            $value = $featureOverrides[$featureKey]
                ?? (PlanFeatureCatalog::isBooleanKey($featureKey) ? 'false' : '0');

            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                'feature_key' => $featureKey,
                'feature_value' => $value,
                'value_type' => PlanFeatureCatalog::valueType($featureKey),
            ]);
        }

        return Tenant::query()->create([
            'name' => 'Plan Feature Tenant',
            'slug' => 'plan-feature-tenant',
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'plan_id' => $plan->id,
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
            'name' => 'Tenant User',
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
