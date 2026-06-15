<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Platform\TenantProvisioningService;
use Database\Seeders\Central\PlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformTenantImpersonationTest extends TestCase
{
    private SuperAdmin $admin;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runCentralMigrations();
        Artisan::call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        $this->plan = Plan::query()->firstOrFail();
        $this->admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    public function test_platform_admin_can_impersonate_tenant_owner(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $tenantPath = storage_path('framework/testing/impersonate-tenant.sqlite');
        @unlink($tenantPath);

        $tenant = app(TenantProvisioningService::class)->create([
            'name' => 'Impersonate Atelier',
            'slug' => 'impersonate-atelier',
            'plan_id' => $this->plan->id,
            'database_name' => $tenantPath,
        ]);

        app(TenantProvisioningService::class)->migrate($tenant);
        app(TenantProvisioningService::class)->seedAdmin($tenant, [
            'admin_email' => 'owner@impersonate.test',
            'admin_password' => 'OwnerPass123!',
            'admin_name' => 'Owner User',
        ]);

        $response = $this->postJson("/api/platform/tenants/{$tenant->id}/impersonate", [], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.impersonation', true)
            ->assertJsonPath('data.tenant.slug', 'impersonate-atelier')
            ->assertJsonPath('data.user.email', 'owner@impersonate.test')
            ->assertJsonStructure(['data' => ['token', 'permissions', 'subscription']]);

        $this->assertDatabaseHas('tenant_provisioning_logs', [
            'tenant_id' => $tenant->id,
            'step' => 'admin_impersonation',
            'status' => 'success',
        ], 'central');
    }

    private function prepareSqliteDatabases(): void
    {
        $centralPath = storage_path('framework/testing/central-impersonate.sqlite');
        $tenantTemplatePath = storage_path('framework/testing/tenant-template-impersonate.sqlite');
        @unlink($centralPath);
        @unlink($tenantTemplatePath);

        Config::set('database.connections.central.database', $centralPath);
        Config::set('database.connections.tenant.database', $tenantTemplatePath);
        DB::purge('central');
        DB::purge('tenant');
    }

    private function runCentralMigrations(): void
    {
        Artisan::call('migrate', [
            '--database' => 'central',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
}
