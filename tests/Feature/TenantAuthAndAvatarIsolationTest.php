<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantUserAvatarService;
use App\Services\Tenant\TenantUserDirectoryService;
use App\Support\TenantMessages;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantAuthAndAvatarIsolationTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantADatabasePath;

    private string $tenantBDatabasePath;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenantA = $this->createTenant('tenant-a', $this->tenantADatabasePath);
        $this->tenantB = $this->createTenant('tenant-b', $this->tenantBDatabasePath);
    }

    public function test_login_without_workspace_uses_x_tenant_header(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');

        $this->postJson('/api/tenant/login', [
            'email' => 'owner@a.test',
            'password' => 'secret123',
        ], $this->tenantHeaders($this->tenantA))
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', 'tenant-a');
    }

    public function test_login_rejects_missing_tenant_context(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');

        $this->postJson('/api/tenant/login', [
            'email' => 'owner@a.test',
            'password' => 'secret123',
        ])->assertStatus(400)
            ->assertJsonPath('message', TenantMessages::CONTEXT_REQUIRED);
    }

    public function test_me_returns_user_for_matching_tenant(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');
        $token = $this->loginAndGetToken($this->tenantA, 'owner@a.test', 'secret123');

        $this->getJson('/api/tenant/me', $this->authenticatedHeaders($this->tenantA, $token))
            ->assertOk()
            ->assertJsonPath('data.user.email', 'owner@a.test')
            ->assertJsonPath('data.tenant.slug', 'tenant-a');
    }

    public function test_token_cannot_access_other_tenant_me(): void
    {
        $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');
        $this->seedTenantUser($this->tenantB, 'owner@b.test', 'secret123');

        $token = $this->loginAndGetToken($this->tenantA, 'owner@a.test', 'secret123');

        $this->getJson('/api/tenant/me', $this->authenticatedHeaders($this->tenantB, $token))
            ->assertForbidden()
            ->assertJsonPath('message', TenantMessages::TOKEN_MISMATCH);
    }

    public function test_avatar_paths_are_isolated_between_tenants_with_same_user_id(): void
    {
        $userA = $this->seedTenantUser($this->tenantA, 'owner@a.test', 'secret123');
        $userB = $this->seedTenantUser($this->tenantB, 'owner@b.test', 'secret123');

        $this->assertSame(1, $userA->id);
        $this->assertSame(1, $userB->id);

        $tokenA = $this->loginAndGetToken($this->tenantA, 'owner@a.test', 'secret123');
        $tokenB = $this->loginAndGetToken($this->tenantB, 'owner@b.test', 'secret123');

        $avatarA = UploadedFile::fake()->image('avatar-a.jpg');
        $avatarB = UploadedFile::fake()->image('avatar-b.jpg');

        $this->post('/api/tenant/settings/profile/avatar', [
            'avatar' => $avatarA,
        ], $this->authenticatedHeaders($this->tenantA, $tokenA))->assertOk();

        $this->post('/api/tenant/settings/profile/avatar', [
            'avatar' => $avatarB,
        ], $this->authenticatedHeaders($this->tenantB, $tokenB))->assertOk();

        $meA = $this->getJson('/api/tenant/me', $this->authenticatedHeaders($this->tenantA, $tokenA))
            ->assertOk()
            ->json('data.user');

        $meB = $this->getJson('/api/tenant/me', $this->authenticatedHeaders($this->tenantB, $tokenB))
            ->assertOk()
            ->json('data.user');

        $this->assertNotNull($meA['avatar_path']);
        $this->assertNotNull($meB['avatar_path']);
        $this->assertNotSame($meA['avatar_path'], $meB['avatar_path']);
        $this->assertStringContainsString('tenants/'.$this->tenantA->id.'/users/1/avatar/', $meA['avatar_path']);
        $this->assertStringContainsString('tenants/'.$this->tenantB->id.'/users/1/avatar/', $meB['avatar_path']);
        $this->assertNotSame($meA['avatar_url'], $meB['avatar_url']);

        $avatarService = app(TenantUserAvatarService::class);
        $this->assertNull($avatarService->url($meA['avatar_path'], $this->tenantB));
        $this->assertNull($avatarService->url($meB['avatar_path'], $this->tenantA));
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(Tenant $tenant): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $tenant->slug,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authenticatedHeaders(Tenant $tenant, string $token): array
    {
        return array_merge($this->tenantHeaders($tenant), [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    private function loginAndGetToken(Tenant $tenant, string $email, string $password): string
    {
        $response = $this->postJson('/api/tenant/login', [
            'email' => $email,
            'password' => $password,
        ], $this->tenantHeaders($tenant))->assertOk();

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

        $this->centralDatabasePath = $testingPath.'/central-auth-avatar.sqlite';
        $this->tenantADatabasePath = $testingPath.'/tenant-a-auth-avatar.sqlite';
        $this->tenantBDatabasePath = $testingPath.'/tenant-b-auth-avatar.sqlite';

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
