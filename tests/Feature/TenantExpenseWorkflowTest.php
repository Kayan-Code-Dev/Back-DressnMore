<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\CashMovementService;
use App\Services\Tenant\JournalEntryPostingService;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantExpenseWorkflowTest extends TestCase
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
            'expenses.view',
            'expenses.create',
            'expenses.approve',
            'expenses.cancel',
            'expenses.pay',
            'expenses.summary',
            'expenses.export',
        ]);
    }

    public function test_expense_workflow_pending_approve_pay_cancel_summary_export(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $create = $this->postJson('/api/tenant/expenses', [
            'amount' => 500,
            'status' => 'pending',
            'expense_date' => '2026-05-26',
            'description' => 'Pending expense',
        ], $this->tenantHeaders());
        $create->assertCreated()->assertJsonPath('data.status', 'pending');
        $expenseId = (int) $create->json('data.id');

        $this->assertDatabaseMissing('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expenseId,
        ], 'tenant');

        $this->postJson("/api/tenant/expenses/{$expenseId}/approve", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->postJson("/api/tenant/expenses/{$expenseId}/pay", [
            'method' => 'cash',
            'transaction_id' => 'TX-EXP-1',
        ], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_EXPENSE,
            'reference_id' => $expenseId,
            'direction' => 'out',
        ], 'tenant');

        $cancelled = Expense::query()->create([
            'amount' => 100,
            'status' => 'cancelled',
            'expense_date' => '2026-05-26',
        ]);

        $this->postJson("/api/tenant/expenses/{$cancelled->id}/pay", [], $this->tenantHeaders())
            ->assertStatus(422);

        $this->getJson('/api/tenant/expenses/summary', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_amount', 'pending_amount', 'approved_amount', 'paid_amount', 'cancelled_amount', 'by_category']]);

        $response = $this->get('/api/tenant/expenses/export', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('expenses.csv', (string) $response->headers->get('content-disposition'));
    }

    public function test_paid_expense_cannot_be_paid_twice_or_change_payment_details(): void
    {
        $expense = Expense::query()->create([
            'amount' => 250,
            'status' => Expense::STATUS_APPROVED,
            'expense_date' => '2026-05-26',
            'description' => 'Approved expense',
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/expenses/{$expense->id}/pay", [
            'method' => 'cash',
            'transaction_id' => 'PAY-ONCE',
        ], $this->tenantHeaders())->assertOk();

        $this->postJson("/api/tenant/expenses/{$expense->id}/pay", [
            'method' => 'bank_transfer',
            'transaction_id' => 'PAY-TWICE',
        ], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.expense.0', 'Expense is already paid. Reverse or cancel the posted payment before changing payment details.');

        $expense->refresh();
        $this->assertSame('cash', $expense->method);
        $this->assertSame('PAY-ONCE', $expense->transaction_id);
        $this->assertSame(1, CashMovement::query()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->count());
        $this->assertSame(1, JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_EXPENSE)
            ->where('source_id', $expense->id)
            ->count());
    }

    public function test_financial_source_posting_is_idempotent(): void
    {
        $expense = Expense::query()->create([
            'amount' => 175,
            'status' => Expense::STATUS_PAID,
            'method' => 'cash',
            'expense_date' => '2026-05-27',
            'description' => 'Already paid expense',
            'paid_at' => '2026-05-27 09:00:00',
        ]);

        app(CashMovementService::class)->syncExpenseMovement($expense);
        app(CashMovementService::class)->syncExpenseMovement($expense->refresh());
        app(JournalEntryPostingService::class)->postFromExpense($expense->refresh());
        app(JournalEntryPostingService::class)->postFromExpense($expense->refresh());

        $this->assertSame(1, CashMovement::query()
            ->where('reference_type', CashMovement::REFERENCE_EXPENSE)
            ->where('reference_id', $expense->id)
            ->count());
        $this->assertSame(1, JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_EXPENSE)
            ->where('source_id', $expense->id)
            ->count());
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-expense-workflow.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-expense-workflow.sqlite';
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
