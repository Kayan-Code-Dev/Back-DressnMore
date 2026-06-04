<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use Carbon\CarbonImmutable;
use Database\Seeders\Central\PlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformTenantProvisioningTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantTemplateDatabasePath;

    private SuperAdmin $admin;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runCentralMigrations();
        $this->seedPlans();
        $this->admin = $this->createSuperAdmin();
    }

    public function test_platform_admin_can_create_tenant_via_api(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $tenantDatabaseName = 'provisioned_tenant_api';
        $storedTenantDatabaseName = $tenantDatabaseName.'.sqlite';
        $tenantDatabasePath = storage_path('framework/tenants/'.$storedTenantDatabaseName);
        @unlink($tenantDatabasePath);

        $response = $this->postJson('/api/platform/tenants', [
            'name' => 'Atelier Cairo',
            'slug' => 'atelier-cairo',
            'plan_id' => $this->plan->id,
            'database_name' => $tenantDatabaseName,
            'subscription_starts_at' => '2026-05-26 00:00:00',
            'subscription_ends_at' => '2026-06-26 00:00:00',
            'metadata' => [
                'source' => 'api',
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tenant created')
            ->assertJsonPath('data.slug', 'atelier-cairo')
            ->assertJsonPath('data.status', 'provisioning');

        $tenantId = (int) $response->json('data.id');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'status' => 'provisioning',
            'database_name' => $storedTenantDatabaseName,
        ], 'central');
        $this->assertDatabaseHas('tenant_provisioning_logs', [
            'tenant_id' => $tenantId,
            'step' => 'tenant_created',
            'status' => 'success',
        ], 'central');

        $migrateResponse = $this->postJson("/api/platform/tenants/{$tenantId}/migrate", [], [
            'Accept' => 'application/json',
        ]);

        $migrateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'status' => 'active',
        ], 'central');
        $this->assertDatabaseHas('tenant_provisioning_logs', [
            'tenant_id' => $tenantId,
            'step' => 'migration_completed',
            'status' => 'success',
        ], 'central');

        $this->assertFileExists($tenantDatabasePath);
    }

    public function test_platform_tenant_create_rejects_absolute_database_path(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/platform/tenants', [
            'name' => 'Unsafe Tenant',
            'slug' => 'unsafe-tenant',
            'plan_id' => $this->plan->id,
            'database_name' => storage_path('framework/testing/unsafe.sqlite'),
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['database_name']);
    }

    public function test_seed_response_hides_password_and_blocks_cross_tenant_email_reassignment(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $firstTenant = $this->createProvisionedTenant('seed-one');
        $secondTenant = $this->createProvisionedTenant('seed-two');

        $firstResponse = $this->postJson("/api/platform/tenants/{$firstTenant->id}/seed", [
            'admin_email' => 'owner@example.test',
            'admin_password' => 'secret-password',
        ], [
            'Accept' => 'application/json',
        ]);

        $firstResponse->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.admin.password')
            ->assertJsonPath('data.email', 'owner@example.test');

        $this->postJson("/api/platform/tenants/{$secondTenant->id}/seed", [
            'admin_email' => 'owner@example.test',
            'admin_password' => 'secret-password',
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('tenant_user_directory', [
            'tenant_id' => $firstTenant->id,
            'email' => 'owner@example.test',
            'status' => 'active',
        ], 'central');
        $this->assertSame(1, TenantUserDirectory::query()->where('email', 'owner@example.test')->count());
    }

    public function test_inactive_platform_admin_token_is_forbidden(): void
    {
        $this->admin->forceFill(['status' => 'inactive'])->save();
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson('/api/platform/tenants', [
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_platform_admin_can_list_tenants(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'plan_id' => $this->plan->id,
            'database_name' => storage_path('framework/testing/tenant-one.sqlite'),
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);

        $response = $this->getJson('/api/platform/tenants?search=tenant&status=active', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'tenant-one');
    }

    public function test_platform_admin_can_suspend_tenant(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $tenant = $this->createTenant('active');

        $response = $this->postJson("/api/platform/tenants/{$tenant->id}/suspend", [], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'suspended');
    }

    public function test_platform_admin_can_activate_tenant(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $tenant = $this->createTenant('suspended');

        $response = $this->postJson("/api/platform/tenants/{$tenant->id}/activate", [], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_platform_admin_can_renew_tenant(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $tenant = $this->createTenant('expired');

        $response = $this->postJson("/api/platform/tenants/{$tenant->id}/renew", [
            'days' => 45,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $tenant->refresh();
        $this->assertNotNull($tenant->subscription_ends_at);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-platform-tenants.sqlite';
        $this->tenantTemplateDatabasePath = $testingPath.'/tenant-template-platform-tenants.sqlite';

        @unlink($this->centralDatabasePath);
        @unlink($this->tenantTemplateDatabasePath);

        touch($this->centralDatabasePath);
        touch($this->tenantTemplateDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => $this->centralDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantTemplateDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('central');
        DB::purge('tenant');
    }

    private function runCentralMigrations(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);
    }

    private function seedPlans(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'central',
            '--class' => PlanSeeder::class,
            '--force' => true,
        ]);

        $this->plan = Plan::query()->where('slug', 'basic')->firstOrFail();
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createTenant(string $status): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Managed Tenant',
            'slug' => 'managed-'.uniqid(),
            'plan_id' => $this->plan->id,
            'database_name' => storage_path('framework/testing/managed-'.uniqid().'.sqlite'),
            'status' => $status,
            'subscription_starts_at' => CarbonImmutable::now()->subMonth(),
            'subscription_ends_at' => CarbonImmutable::now()->subDay(),
        ]);
    }

    private function createProvisionedTenant(string $slug): Tenant
    {
        $tenantDatabasePath = storage_path('framework/testing/'.$slug.'.sqlite');
        @unlink($tenantDatabasePath);

        $tenant = Tenant::query()->create([
            'name' => 'Seed '.$slug,
            'slug' => $slug,
            'plan_id' => $this->plan->id,
            'database_name' => $tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(30),
        ]);

        touch($tenantDatabasePath);
        Config::set('database.connections.tenant.database', $tenantDatabasePath);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        return $tenant;
    }
}
