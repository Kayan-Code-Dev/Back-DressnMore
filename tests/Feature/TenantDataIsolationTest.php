<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Models\Central\SuperAdmin;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantUserDirectoryService;
use App\Support\TenantMessages;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantDataIsolationTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantADatabasePath;

    private string $tenantBDatabasePath;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenantA = $this->createTenant('tenant-a', $this->tenantADatabasePath);
        $this->tenantB = $this->createTenant('tenant-b', $this->tenantBDatabasePath);
    }

    public function test_login_works_with_email_and_password_only(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');

        $this->postJson('/api/tenant/login', [
            'email' => 'owner@a.test',
            'password' => 'secret123',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', 'tenant-a');
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');

        $this->postJson('/api/tenant/login', [
            'email' => 'unknown@test.com',
            'password' => 'secret123',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_login_succeeds_only_for_matching_tenant_and_directory(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');

        $this->postJson('/api/tenant/login', [
            'email' => 'owner@a.test',
            'password' => 'secret123',
        ], [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenantA->slug,
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', 'tenant-a');
    }

    public function test_token_is_bound_to_tenant_and_cannot_access_other_tenant(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');
        $this->seedTenantUser($this->tenantB, 'owner@b.test', 'secret123');

        $token = $this->loginAndGetToken($this->tenantA, 'owner@a.test', 'secret123');

        $this->getJson('/api/tenant/customers?per_page=1', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $this->tenantB->slug,
            'Accept' => 'application/json',
        ])->assertForbidden()
            ->assertJsonPath('message', TenantMessages::TOKEN_MISMATCH);
    }

    public function test_platform_token_cannot_access_tenant_api(): void
    {
        $platformAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-token@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        Sanctum::actingAs($platformAdmin, ['*']);

        $this->getJson('/api/tenant/subscription/overview', [
            'X-Tenant' => $this->tenantA->slug,
            'Accept' => 'application/json',
        ])->assertForbidden()
            ->assertJsonPath('message', TenantMessages::TOKEN_MISMATCH);
    }

    public function test_tenant_health_does_not_expose_database_metadata(): void
    {
        $this->getJson('/api/tenant/health', [
            'X-Tenant' => $this->tenantA->slug,
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ok', true)
            ->assertJsonMissingPath('data.tenant_database_name')
            ->assertJsonMissingPath('data.tenant_slug')
            ->assertJsonMissingPath('data.tenant_name');
    }

    public function test_tenant_data_is_stored_in_separate_databases(): void
    {
        $this->connectTenant($this->tenantA);
        Customer::query()->create([
            'customer_code' => 'A-001',
            'name' => 'Tenant A Customer',
            'phone' => '0500000001',
            'status' => 'active',
        ]);

        $this->connectTenant($this->tenantB);
        $this->assertSame(0, Customer::query()->count());

        Customer::query()->create([
            'customer_code' => 'B-001',
            'name' => 'Tenant B Customer',
            'phone' => '0500000002',
            'status' => 'active',
        ]);

        $this->connectTenant($this->tenantA);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('Tenant A Customer', Customer::query()->value('name'));
    }

    public function test_directory_enforces_one_email_to_one_tenant(): void
    {
        $this->seedTenantUser($this->tenantA, 'shared@test.com', 'secret123');

        $this->expectException(\Illuminate\Database\QueryException::class);

        TenantUserDirectory::query()->create([
            'email' => 'shared@test.com',
            'tenant_id' => $this->tenantB->id,
            'status' => 'active',
        ]);
    }

    private function loginAndGetToken(Tenant $tenant, string $email, string $password): string
    {
        $response = $this->postJson('/api/tenant/login', [
            'email' => $email,
            'password' => $password,
        ], ['Accept' => 'application/json'])->assertOk();

        $token = $response->json('data.token');
        $this->assertIsString($token);

        return $token;
    }

    private function seedTenantUser(Tenant $tenant, string $email, string $password): User
    {
        $this->connectTenant($tenant);
        $ownerRole = Role::query()->where('slug', 'owner')->first();
        $user = User::query()->create([
            'name' => 'Owner',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);
        if ($ownerRole) {
            $user->roles()->sync([$ownerRole->id]);
        }
        app(TenantUserDirectoryService::class)->register($tenant, $email);

        return $user;
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-isolation.sqlite';
        $this->tenantADatabasePath = $testingPath.'/tenant-a-isolation.sqlite';
        $this->tenantBDatabasePath = $testingPath.'/tenant-b-isolation.sqlite';

        foreach ([
            $this->centralDatabasePath,
            $this->tenantADatabasePath,
            $this->tenantBDatabasePath,
        ] as $path) {
            @unlink($path);
            touch($path);
        }

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => $this->centralDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantADatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('central');
        DB::purge('tenant');
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        foreach ([$this->tenantADatabasePath, $this->tenantBDatabasePath] as $path) {
            $this->migrateTenantDatabase($path);
        }
    }

    private function migrateTenantDatabase(string $databasePath): void
    {
        Config::set('database.connections.tenant.database', $databasePath);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function seedTenantPermissions(): void
    {
        foreach ([$this->tenantADatabasePath, $this->tenantBDatabasePath] as $path) {
            Config::set('database.connections.tenant.database', $path);
            DB::purge('tenant');
            DB::reconnect('tenant');
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => TenantRolePermissionSeeder::class,
                '--force' => true,
            ]);
        }
    }

    private function createTenant(string $slug, string $databasePath): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant '.$slug,
            'slug' => $slug,
            'database_name' => $databasePath,
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
}
