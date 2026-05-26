<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
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

class TenantExpenseTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->ownerUser = $this->createTenantUserWithPermissions([
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
        ]);
    }

    public function test_tenant_user_can_list_expenses(): void
    {
        Expense::query()->create([
            'amount' => 250,
            'method' => 'cash',
            'reference' => 'EXP-001',
            'expense_date' => '2026-05-26',
            'description' => 'Electricity bill',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson('/api/tenant/expenses', $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tenant_user_can_create_expense(): void
    {
        $category = ExpenseCategory::query()->create(['name' => 'Utilities', 'status' => 'active']);
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/expenses', [
            'expense_category_id' => $category->id,
            'amount' => 300,
            'method' => 'cash',
            'reference' => 'EXP-200',
            'expense_date' => '2026-05-26',
            'description' => 'Water bill',
            'notes' => 'May bill',
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('message', 'Expense created')
            ->assertJsonPath('data.reference', 'EXP-200');

        $this->assertDatabaseHas('expenses', [
            'reference' => 'EXP-200',
            'amount' => 300,
        ], 'tenant');
    }

    public function test_creating_expense_creates_cash_movement_out(): void
    {
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->postJson('/api/tenant/expenses', [
            'amount' => 400,
            'method' => 'cash',
            'reference' => 'EXP-OUT-01',
            'expense_date' => '2026-05-26',
            'description' => 'Fabric purchase',
        ], $this->tenantHeaders());

        $response->assertCreated();
        $expenseId = (int) $response->json('data.id');

        $this->assertDatabaseHas('cash_movements', [
            'type' => CashMovement::TYPE_EXPENSE,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 400,
            'method' => 'cash',
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expenseId,
        ], 'tenant');
    }

    public function test_tenant_user_can_update_expense(): void
    {
        $expense = $this->createExpense();
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->putJson("/api/tenant/expenses/{$expense->id}", [
            'expense_category_id' => null,
            'amount' => 550,
            'method' => 'instapay',
            'reference' => 'EXP-UPD-01',
            'expense_date' => '2026-05-27',
            'description' => 'Updated expense',
            'notes' => 'updated note',
        ], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Expense updated')
            ->assertJsonPath('data.amount', '550.00')
            ->assertJsonPath('data.method', 'instapay');
    }

    public function test_updating_expense_updates_related_cash_movement(): void
    {
        $expense = $this->createExpense();
        Sanctum::actingAs($this->ownerUser, ['*']);

        $this->putJson("/api/tenant/expenses/{$expense->id}", [
            'expense_category_id' => null,
            'amount' => 700,
            'method' => 'bank_transfer',
            'reference' => 'EXP-UPD-CM',
            'expense_date' => '2026-05-28',
            'description' => 'Updated with movement',
            'notes' => 'updated movement note',
        ], $this->tenantHeaders())->assertOk();

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expense->id,
            'amount' => 700,
            'method' => 'bank_transfer',
            'reference' => 'EXP-UPD-CM',
        ], 'tenant');
    }

    public function test_tenant_user_can_delete_expense(): void
    {
        $expense = $this->createExpense();
        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->deleteJson("/api/tenant/expenses/{$expense->id}", [], $this->tenantHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Expense deleted');

        $this->assertSoftDeleted('expenses', ['id' => $expense->id], 'tenant');
        $this->assertSoftDeleted('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expense->id,
        ], 'tenant');
    }

    public function test_filters_work(): void
    {
        $categoryA = ExpenseCategory::query()->create(['name' => 'Category A', 'status' => 'active']);
        $categoryB = ExpenseCategory::query()->create(['name' => 'Category B', 'status' => 'active']);

        Expense::query()->create([
            'expense_category_id' => $categoryA->id,
            'amount' => 100,
            'method' => 'cash',
            'reference' => 'EXP-FILTER-1',
            'expense_date' => '2026-05-25',
            'description' => 'Fabric order',
            'notes' => 'primary',
        ]);
        Expense::query()->create([
            'expense_category_id' => $categoryB->id,
            'amount' => 200,
            'method' => 'instapay',
            'reference' => 'EXP-FILTER-2',
            'expense_date' => '2026-05-30',
            'description' => 'Rent payment',
            'notes' => 'secondary',
        ]);

        Sanctum::actingAs($this->ownerUser, ['*']);

        $response = $this->getJson(
            "/api/tenant/expenses?search=fabric&expense_category_id={$categoryA->id}&method=cash&date_from=2026-05-24&date_to=2026-05-26",
            $this->tenantHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference', 'EXP-FILTER-1');
    }

    public function test_user_without_permission_is_rejected(): void
    {
        $restrictedUser = $this->createTenantUserWithPermissions(['dashboard.view']);
        Sanctum::actingAs($restrictedUser, ['*']);

        $response = $this->getJson('/api/tenant/expenses', $this->tenantHeaders());

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createExpense(): Expense
    {
        $expense = Expense::query()->create([
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'EXP-BASE-01',
            'expense_date' => '2026-05-26',
            'description' => 'Base expense',
            'notes' => 'base note',
        ]);

        CashMovement::query()->create([
            'type' => CashMovement::TYPE_EXPENSE,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 500,
            'method' => 'cash',
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expense->id,
            'reference' => 'EXP-BASE-01',
            'movement_date' => '2026-05-26 00:00:00',
            'description' => 'Base expense',
            'notes' => 'base note',
        ]);

        return $expense;
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-expenses.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-expenses.sqlite';

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
        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

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
        $role = Role::query()->create([
            'name' => 'Role '.uniqid(),
            'slug' => 'role-'.uniqid(),
        ]);

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

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
     * @return array<string, string>
     */
    private function tenantHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
        ];
    }
}
