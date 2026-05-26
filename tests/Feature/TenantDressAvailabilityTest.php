<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
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

class TenantDressAvailabilityTest extends TestCase
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
        $this->user = $this->createTenantUserWithPermissions(['dresses.view', 'dresses.export']);
    }

    public function test_available_for_date_and_unavailable_days_and_order_history(): void
    {
        $bookedDress = Dress::query()->create(['code' => 'DR-BOOKED', 'name' => 'Booked', 'status' => 'available']);
        $freeDress = Dress::query()->create(['code' => 'DR-FREE', 'name' => 'Free', 'status' => 'available']);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-BOOK-1',
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-05',
            'total' => 100,
            'remaining_amount' => 100,
        ]);
        $invoice->items()->create([
            'dress_id' => $bookedDress->id,
            'description' => 'Booked item',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/tenant/dresses/available-for-date?date=2026-06-03', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonMissing(['code' => 'DR-BOOKED'])
            ->assertJsonFragment(['code' => 'DR-FREE']);

        $this->getJson("/api/tenant/dresses/{$bookedDress->id}/unavailable-days", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.ranges.0.invoice_number', 'INV-BOOK-1')
            ->assertJsonPath('data.days.0', '2026-06-01');

        $this->getJson("/api/tenant/dresses/{$bookedDress->id}/order-history", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', 'INV-BOOK-1');
    }

    public function test_dresses_export_returns_csv(): void
    {
        Dress::query()->create(['code' => 'DR-EXP', 'name' => 'Export Dress', 'status' => 'available']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->get('/api/tenant/dresses/export', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('dresses.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('DR-EXP', $response->streamedContent());
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-dress-availability.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-dress-availability.sqlite';
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
