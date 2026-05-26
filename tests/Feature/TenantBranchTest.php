<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Branch;
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

class TenantBranchTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $branchUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->branchUser = $this->createTenantUserWithPermissions([
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'branches.export',
        ]);
    }

    public function test_tenant_user_can_crud_branches_and_filter(): void
    {
        Sanctum::actingAs($this->branchUser, ['*']);

        $create = $this->postJson('/api/tenant/branches', [
            'branch_code' => 'BR-001',
            'name' => 'Main Branch',
            'phone' => '0100',
            'vat_enabled' => true,
            'vat_type' => 'percentage',
            'vat_value' => 14,
            'currency' => 'EGP',
            'currency_id' => 1,
            'city_id' => 2,
            'address' => 'Main Street',
            'status' => 'active',
        ], $this->tenantHeaders());
        $create->assertCreated()->assertJsonPath('data.branch_code', 'BR-001');
        $branchId = (int) $create->json('data.id');

        $this->putJson("/api/tenant/branches/{$branchId}", [
            'branch_code' => 'BR-001',
            'name' => 'Main Branch Updated',
            'phone' => '0101',
            'vat_enabled' => true,
            'vat_type' => 'fixed',
            'vat_value' => 50,
            'currency' => 'EGP',
            'currency_id' => 1,
            'city_id' => 2,
            'address' => 'Updated Street',
            'status' => 'active',
        ], $this->tenantHeaders())->assertOk()->assertJsonPath('data.vat_type', 'fixed');

        Branch::query()->create([
            'name' => 'Inactive Branch',
            'branch_code' => 'BR-002',
            'status' => 'inactive',
            'currency_id' => 2,
            'city_id' => 3,
        ]);

        $this->getJson('/api/tenant/branches?search=main&status=active&city_id=2&currency_id=1', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Main Branch Updated');

        $this->deleteJson("/api/tenant/branches/{$branchId}", [], $this->tenantHeaders())->assertOk();
        $this->assertSoftDeleted('branches', ['id' => $branchId], 'tenant');
    }

    public function test_branch_export_returns_csv(): void
    {
        Branch::query()->create(['name' => 'Export Branch', 'branch_code' => 'BR-EXP', 'status' => 'active']);
        Sanctum::actingAs($this->branchUser, ['*']);

        $response = $this->get('/api/tenant/branches/export', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('branches.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Export Branch', $response->streamedContent());
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-branches-ui.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-branches-ui.sqlite';
        @unlink($this->centralDatabasePath);
        @unlink($this->tenantDatabasePath);
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
     * @return array<string,string>
     */
    private function tenantHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => $this->tenant->slug];
    }
}
