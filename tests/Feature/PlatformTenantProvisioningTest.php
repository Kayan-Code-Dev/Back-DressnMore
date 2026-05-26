<?php

namespace Tests\Feature;

use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runCentralMigrations();
        $this->admin = $this->createSuperAdmin();
    }

    public function test_platform_admin_can_create_tenant_via_api(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $tenantDatabasePath = storage_path('framework/testing/provisioned-tenant-api.sqlite');
        @unlink($tenantDatabasePath);

        $response = $this->postJson('/api/platform/tenants', [
            'name' => 'Atelier Cairo',
            'slug' => 'atelier-cairo',
            'database_name' => $tenantDatabasePath,
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
            ->assertJsonPath('message', 'Tenant provisioned')
            ->assertJsonPath('data.slug', 'atelier-cairo')
            ->assertJsonPath('data.status', 'active');

        $tenantId = (int) $response->json('data.id');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'status' => 'active',
            'database_name' => $tenantDatabasePath,
        ], 'central');
        $this->assertDatabaseHas('tenant_provisioning_logs', [
            'tenant_id' => $tenantId,
            'step' => 'provisioning_completed',
            'status' => 'success',
        ], 'central');

        $this->assertFileExists($tenantDatabasePath);
    }

    public function test_platform_admin_can_list_tenants(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        Tenant::query()->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
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
            'database_name' => storage_path('framework/testing/managed-'.uniqid().'.sqlite'),
            'status' => $status,
            'subscription_starts_at' => CarbonImmutable::now()->subMonth(),
            'subscription_ends_at' => CarbonImmutable::now()->subDay(),
        ]);
    }
}
