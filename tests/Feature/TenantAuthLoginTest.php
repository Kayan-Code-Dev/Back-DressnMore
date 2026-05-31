<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantUserDirectoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantAuthLoginTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
    }

    public function test_tenant_can_login_with_email_only_without_workspace(): void
    {
        $tenant = $this->createTenant('atelier-alpha');
        $this->connectTenant($tenant);
        $this->createOwnerUser('owner@atelier.test', 'secret123');

        app(TenantUserDirectoryService::class)->register($tenant, 'owner@atelier.test');

        $response = $this->postJson('/api/tenant/login', [
            'email' => 'owner@atelier.test',
            'password' => 'secret123',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.slug', 'atelier-alpha')
            ->assertJsonPath('data.user.email', 'owner@atelier.test')
            ->assertJsonPath('data.subscription.lifecycle_status', 'active');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_tenant_login_rejects_invalid_credentials_without_workspace(): void
    {
        $tenant = $this->createTenant('atelier-beta');
        $this->connectTenant($tenant);
        $this->createOwnerUser('admin@beta.test', 'secret123');
        app(TenantUserDirectoryService::class)->register($tenant, 'admin@beta.test');

        $response = $this->postJson('/api/tenant/login', [
            'email' => 'admin@beta.test',
            'password' => 'wrong-password',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_login_falls_back_to_metadata_admin_email(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Legacy Tenant',
            'slug' => 'legacy-tenant',
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
            'metadata' => ['admin_email' => 'legacy@tenant.test'],
        ]);

        $this->connectTenant($tenant);
        $this->createOwnerUser('legacy@tenant.test', 'legacy-pass');

        $this->assertSame(0, TenantUserDirectory::query()->count());

        $response = $this->postJson('/api/tenant/login', [
            'email' => 'legacy@tenant.test',
            'password' => 'legacy-pass',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.tenant.slug', 'legacy-tenant');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-auth-login.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-auth-login.sqlite';
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
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
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

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant '.$slug,
            'slug' => $slug,
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(30),
        ]);
    }

    private function connectTenant(Tenant $tenant): void
    {
        Config::set('database.connections.tenant.database', $tenant->database_name);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function createOwnerUser(string $email, string $password): User
    {
        $ownerRole = Role::query()->where('slug', 'owner')->first();
        $user = User::query()->create([
            'name' => 'Owner User',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        if ($ownerRole) {
            $user->roles()->sync([$ownerRole->id]);
        }

        return $user;
    }
}
