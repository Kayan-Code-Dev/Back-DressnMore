<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\AccountSeeder;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantJournalEntryTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $user;

    /** @var array<int, int> */
    private array $accountIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUserWithPermissions([
            'accounting.journal_entries.view',
            'accounting.journal_entries.create',
            'accounting.journal_entries.update',
            'accounting.journal_entries.approve',
            'accounting.journal_entries.cancel',
            'accounting.journal_entries.reverse',
            'accounting.journal_entries.export',
        ]);
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => AccountSeeder::class,
            '--force' => true,
        ]);
        $this->accountIds = Account::query()->orderBy('id')->pluck('id')->all();
    }

    public function test_can_save_draft_unbalanced_journal_entry(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/accounting/journal-entries', [
            'entry_date' => '2026-05-31',
            'description' => 'Draft unbalanced entry',
            'lines' => [
                ['account_id' => $this->accountIds[0], 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountIds[1], 'credit' => 50, 'debit' => 0],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_balanced', false);
    }

    public function test_cannot_approve_unbalanced_journal_entry(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $create = $this->postJson('/api/tenant/accounting/journal-entries', [
            'entry_date' => '2026-05-31',
            'description' => 'Unbalanced draft',
            'lines' => [
                ['account_id' => $this->accountIds[0], 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->accountIds[1], 'credit' => 50, 'debit' => 0],
            ],
        ], $this->tenantHeaders())->assertCreated();

        $entryId = (int) $create->json('data.id');

        $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/approve", [], $this->tenantHeaders())
            ->assertStatus(422);
    }

    public function test_approved_balanced_entry_succeeds(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $create = $this->postJson('/api/tenant/accounting/journal-entries', [
            'entry_date' => '2026-05-31',
            'description' => 'Balanced draft',
            'lines' => [
                ['account_id' => $this->accountIds[0], 'debit' => 250, 'credit' => 0],
                ['account_id' => $this->accountIds[1], 'credit' => 250, 'debit' => 0],
            ],
        ], $this->tenantHeaders())->assertCreated();

        $entryId = (int) $create->json('data.id');

        $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/approve", [], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.is_balanced', true);
    }

    public function test_approved_entry_cannot_be_edited(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $entryId = $this->createApprovedEntry();

        $this->putJson("/api/tenant/accounting/journal-entries/{$entryId}", [
            'description' => 'Should fail',
        ], $this->tenantHeaders())->assertStatus(422);
    }

    public function test_cancelled_entry_cannot_be_edited_or_approved(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $entryId = $this->createApprovedEntry();

        $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/cancel", [
            'cancellation_reason' => 'Wrong posting',
        ], $this->tenantHeaders())->assertOk();

        $this->putJson("/api/tenant/accounting/journal-entries/{$entryId}", [
            'description' => 'Should fail',
        ], $this->tenantHeaders())->assertStatus(422);

        $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/approve", [], $this->tenantHeaders())
            ->assertStatus(422);
    }

    public function test_reversal_entry_creates_opposite_lines(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $entryId = $this->createApprovedEntry();

        $response = $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/reverse", [], $this->tenantHeaders())
            ->assertCreated()
            ->assertJsonPath('data.type', 'reversal')
            ->assertJsonPath('data.status', 'approved');

        $lines = $response->json('data.lines');
        $this->assertSame(250.0, (float) $lines[0]['credit']);
        $this->assertSame(250.0, (float) $lines[1]['debit']);
    }

    public function test_summary_and_filters_work(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->createApprovedEntry();

        $this->getJson('/api/tenant/accounting/journal-entries/summary', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.approved_count', 1)
            ->assertJsonPath('data.total_debit', 250)
            ->assertJsonPath('data.total_credit', 250);

        $this->getJson('/api/tenant/accounting/journal-entries?status=approved', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_export_respects_filters(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->createApprovedEntry();

        $response = $this->get('/api/tenant/accounting/journal-entries/export?status=approved', $this->tenantHeaders());
        $response->assertOk();
        $this->assertStringContainsString('journal-entries.csv', (string) $response->headers->get('content-disposition'));
    }

    private function createApprovedEntry(): int
    {
        $create = $this->postJson('/api/tenant/accounting/journal-entries', [
            'entry_date' => '2026-05-31',
            'description' => 'Balanced approved',
            'lines' => [
                ['account_id' => $this->accountIds[0], 'debit' => 250, 'credit' => 0],
                ['account_id' => $this->accountIds[1], 'credit' => 250, 'debit' => 0],
            ],
        ], $this->tenantHeaders())->assertCreated();

        $entryId = (int) $create->json('data.id');
        $this->postJson("/api/tenant/accounting/journal-entries/{$entryId}/approve", [], $this->tenantHeaders())
            ->assertOk();

        return $entryId;
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }
        $this->centralDatabasePath = $testingPath.'/central-journal-entry.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-journal-entry.sqlite';
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

    public function test_create_from_source_is_idempotent(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $service = app(\App\Services\Tenant\JournalEntryService::class);
        $header = [
            'entry_date' => '2026-05-31',
            'source_type' => JournalEntry::SOURCE_PAYMENT,
            'source_id' => 999,
            'reference_number' => 'PAY-999',
            'description' => 'Test payment posting',
        ];
        $lines = [
            ['account_id' => $this->accountIds[0], 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accountIds[1], 'credit' => 100, 'debit' => 0],
        ];

        $first = $service->createFromSource($header, $lines, $this->user->id);
        $second = $service->createFromSource($header, $lines, $this->user->id);

        $this->assertSame($first->id, $second->id);
    }

    /**
     * @return array<string,string>
     */
    private function tenantHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => $this->tenant->slug];
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $entryId = $this->createApprovedEntry();

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'slug' => 'other',
            'database_name' => storage_path('framework/testing/other-tenant-journal.sqlite'),
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);

        touch($otherTenant->database_name);
        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->getJson("/api/tenant/accounting/journal-entries/{$entryId}", [
            'Accept' => 'application/json',
            'X-Tenant' => $otherTenant->slug,
        ])->assertNotFound();
    }
}
