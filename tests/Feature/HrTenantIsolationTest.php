<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\Permission;
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
use Tests\TestCase;

class HrTenantIsolationTest extends TestCase
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

    public function test_tenant_a_cannot_see_tenant_b_hr_employee(): void
    {
        $this->connectTenant($this->tenantA);
        $userA = $this->createHrUser($this->tenantA, 'owner@a.test');

        $this->connectTenant($this->tenantB);
        $employeeB = HrEmployee::query()->create([
            'employee_code' => 'B-EMP-1',
            'full_name' => 'Tenant B Employee',
            'phone' => '+966500000002',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
        ]);

        $token = $this->createBoundToken($userA, $this->tenantA);

        $this->getJson('/api/tenant/hr/employees/'.$employeeB->id, $this->authenticatedHeaders($this->tenantA, $token))
            ->assertNotFound();
    }

    public function test_tenant_bound_token_cannot_access_other_tenant_hr_routes(): void
    {
        $this->createHrUser($this->tenantA, 'owner@a.test');
        $this->createHrUser($this->tenantB, 'owner@b.test');

        $token = $this->loginAndGetToken('owner@a.test', 'secret123');

        $this->getJson('/api/tenant/hr/employees', $this->authenticatedHeaders($this->tenantB, $token))
            ->assertForbidden()
            ->assertJsonPath('message', TenantMessages::TOKEN_MISMATCH);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $response = $this->postJson('/api/tenant/login', [
            'email' => $email,
            'password' => $password,
        ], ['Accept' => 'application/json'])->assertOk();

        $token = $response->json('data.token');
        $this->assertIsString($token);

        return $token;
    }

    private function createHrUser(Tenant $tenant, string $email): User
    {
        $this->connectTenant($tenant);
        $role = Role::query()->create(['name' => 'HR', 'slug' => 'hr-'.uniqid()]);
        $permissionIds = Permission::query()->whereIn('key', [
            'hr.employees.view',
            'hr.documents.view',
            'hr.settings.view',
        ])->pluck('id')->all();
        $role->permissions()->sync($permissionIds);
        $user = User::query()->create([
            'name' => 'HR User',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);
        app(TenantUserDirectoryService::class)->register($tenant, $email);

        return $user;
    }

    private function createBoundToken(User $user, Tenant $tenant): string
    {
        $this->connectTenant($tenant);
        Config::set('database.connections.central.database', $this->centralDatabasePath);
        DB::purge('central');
        DB::reconnect('central');

        $tokenResult = $user->createToken('hr-test');
        $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

        return $tokenResult->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function authenticatedHeaders(Tenant $tenant, string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->slug,
        ];
    }

    private function connectTenant(Tenant $tenant): void
    {
        Config::set('database.connections.tenant.database', $tenant->database_name);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $suffix = uniqid('hr-iso-', true);
        $this->centralDatabasePath = $testingPath.'/central-'.$suffix.'.sqlite';
        $this->tenantADatabasePath = $testingPath.'/tenant-a-'.$suffix.'.sqlite';
        $this->tenantBDatabasePath = $testingPath.'/tenant-b-'.$suffix.'.sqlite';
        touch($this->centralDatabasePath);
        touch($this->tenantADatabasePath);
        touch($this->tenantBDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite', 'database' => $this->centralDatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite', 'database' => $this->tenantADatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('central');
        DB::purge('tenant');
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        foreach ([$this->tenantADatabasePath, $this->tenantBDatabasePath] as $path) {
            Config::set('database.connections.tenant.database', $path);
            DB::purge('tenant');
            DB::reconnect('tenant');
            Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => base_path('database/migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
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
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);
    }
}
