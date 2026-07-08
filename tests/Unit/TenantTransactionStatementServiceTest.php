<?php

namespace Tests\Unit;

use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Cashbox;
use App\Services\Tenant\TransactionStatementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantTransactionStatementServiceTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
    }

    public function test_summary_opening_balance_includes_movements_before_date_from(): void
    {
        $cashbox = Cashbox::query()->create([
            'name' => 'Unit Test Cashbox',
            'initial_balance' => 100,
            'current_balance' => 140,
            'is_active' => true,
        ]);

        CashMovement::query()->create([
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 50,
            'balance_after' => 150,
            'cashbox_id' => $cashbox->id,
            'movement_date' => '2026-05-20 09:00:00',
            'description' => 'Pre-period income',
            'is_reversed' => false,
        ]);

        CashMovement::query()->create([
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_OUT,
            'amount' => 30,
            'balance_after' => 120,
            'cashbox_id' => $cashbox->id,
            'movement_date' => '2026-05-22 10:00:00',
            'description' => 'Pre-period expense',
            'is_reversed' => false,
        ]);

        CashMovement::query()->create([
            'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashMovement::DIRECTION_IN,
            'amount' => 20,
            'balance_after' => 140,
            'cashbox_id' => $cashbox->id,
            'movement_date' => '2026-05-25 11:00:00',
            'description' => 'Inside period income',
            'is_reversed' => false,
        ]);

        /** @var TransactionStatementService $service */
        $service = app(TransactionStatementService::class);

        $summary = $service->summary([
            'date_from' => '2026-05-25',
            'date_to' => '2026-05-25',
        ]);

        $this->assertSame(120.0, $summary['opening_balance']);
        $this->assertSame(20.0, $summary['total_revenues']);
        $this->assertSame(0.0, $summary['total_expenses']);
        $this->assertSame(140.0, $summary['closing_balance']);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-statement-service.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-statement-service.sqlite';

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
}
