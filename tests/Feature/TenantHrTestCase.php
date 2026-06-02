<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class TenantHrTestCase extends TestCase
{
    protected string $centralDatabasePath;

    protected string $tenantDatabasePath;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    protected function createTenantUserWithPermissions(array $permissionKeys): User
    {
        $role = Role::query()->create(['name' => 'Role '.uniqid(), 'slug' => 'role-'.uniqid()]);
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
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
     * @return list<string>
     */
    protected function allHrPhase1Permissions(): array
    {
        return [
            'hr.view',
            'hr.dashboard.view',
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.update',
            'hr.employees.delete',
            'hr.employees.status',
            'hr.documents.view',
            'hr.documents.upload',
            'hr.documents.delete',
            'hr.settings.view',
            'hr.settings.update',
            'hr.departments.view',
            'hr.departments.create',
            'hr.departments.update',
            'hr.departments.delete',
            'hr.job_titles.view',
            'hr.job_titles.create',
            'hr.job_titles.update',
            'hr.job_titles.delete',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function tenantHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => $this->tenant->slug];
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(User $user): array
    {
        return array_merge($this->tenantHeaders(), [
            'Authorization' => 'Bearer '.$this->createTenantBoundToken($user),
        ]);
    }

    protected function createTenantBoundToken(User $user): string
    {
        Config::set('database.connections.central.database', $this->centralDatabasePath);
        DB::purge('central');
        DB::reconnect('central');

        $tokenResult = $user->createToken('hr-feature-test');
        $tokenResult->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

        return $tokenResult->plainTextToken;
    }

    protected function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $suffix = uniqid('hr-', true);
        $this->centralDatabasePath = $testingPath.'/central-'.$suffix.'.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-'.$suffix.'.sqlite';
        touch($this->centralDatabasePath);
        touch($this->tenantDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite', 'database' => $this->centralDatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite', 'database' => $this->tenantDatabasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('central');
        DB::purge('tenant');
    }

    protected function runMigrations(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function seedTenantPermissions(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => TenantRolePermissionSeeder::class,
            '--force' => true,
        ]);
    }

    protected function createTenant(string $slug = 'demo-hr'): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Demo Tenant',
            'slug' => $slug,
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);
    }
}
