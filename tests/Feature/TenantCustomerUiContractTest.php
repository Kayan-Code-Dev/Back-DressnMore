<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
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

class TenantCustomerUiContractTest extends TestCase
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
        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUserWithPermissions([
            'customers.view',
            'customers.create',
            'customers.export',
        ]);
    }

    public function test_customer_ui_fields_can_be_created_and_filtered(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/tenant/customers', [
            'name' => 'UI Customer',
            'date_of_birth' => '1995-02-01',
            'phone' => '01000000000',
            'phone2' => '01000000001',
            'address' => 'Street 1',
            'city_id' => 10,
            'national_id' => 'N-100',
            'source' => 'instagram',
            'notes' => 'ui test',
            'status' => 'active',
        ], $this->tenantHeaders())->assertCreated();

        Customer::query()->create([
            'name' => 'Other Customer',
            'date_of_birth' => '1980-01-01',
            'source' => 'walk_in',
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/tenant/customers?search=ui&source=instagram&date_of_birth_from=1990-01-01&date_of_birth_to=2000-01-01',
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.phone2', '01000000001')
            ->assertJsonPath('data.0.source', 'instagram')
            ->assertJsonPath('data.0.date_of_birth', '1995-02-01');
    }

    public function test_customers_export_returns_csv_attachment(): void
    {
        Customer::query()->create([
            'name' => 'Export Customer',
            'phone' => '123',
            'source' => 'referral',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->get('/api/tenant/customers/export', $this->tenantHeaders());

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('customers.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Export Customer', $response->streamedContent());
    }

    public function test_user_without_export_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['customers.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->get('/api/tenant/customers/export', $this->tenantHeaders());
        $response->assertStatus(403);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-customers-ui.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-customers-ui.sqlite';

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
