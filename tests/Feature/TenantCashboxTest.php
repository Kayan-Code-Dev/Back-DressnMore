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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantCashboxTest extends TestCase
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
            'cashboxes.view',
            'cashboxes.create',
            'cashboxes.update',
            'cashboxes.delete',
            'cashboxes.recalculate',
            'cashboxes.export',
            'cash_movements.create',
        ]);
    }

    public function test_cashbox_crud_transactions_recalculate_and_exports(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $create = $this->postJson('/api/tenant/cashboxes', [
            'name' => 'Main Cashbox',
            'initial_balance' => 100,
            'is_active' => true,
        ], $this->tenantHeaders());
        $create->assertCreated()->assertJsonPath('data.current_balance', 100);
        $cashboxId = (int) $create->json('data.id');

        $this->postJson('/api/tenant/cash-movements', [
            'type' => 'manual_adjustment',
            'direction' => 'in',
            'amount' => 50,
            'cashbox_id' => $cashboxId,
            'movement_date' => '2026-05-26 09:00:00',
            'description' => 'Opening add',
        ], $this->tenantHeaders())->assertCreated();

        $this->getJson("/api/tenant/cashboxes/{$cashboxId}/transactions", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->postJson("/api/tenant/cashboxes/{$cashboxId}/recalculate", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.current_balance', 150);

        $this->putJson("/api/tenant/cashboxes/{$cashboxId}", [
            'name' => 'Main Cashbox Updated',
            'initial_balance' => 100,
            'is_active' => true,
        ], $this->tenantHeaders())->assertOk()->assertJsonPath('data.name', 'Main Cashbox Updated');

        $this->getJson('/api/tenant/cashboxes/daily-summary', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_in', 'total_out', 'net']]);

        $export = $this->get('/api/tenant/cashboxes/export', $this->tenantHeaders());
        $export->assertOk();
        $this->assertStringContainsString('cashboxes.csv', (string) $export->headers->get('content-disposition'));

        $this->deleteJson("/api/tenant/cashboxes/{$cashboxId}", [], $this->tenantHeaders())->assertOk();
        $this->assertSoftDeleted('cashboxes', ['id' => $cashboxId], 'tenant');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-cashboxes.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-cashboxes.sqlite';
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
