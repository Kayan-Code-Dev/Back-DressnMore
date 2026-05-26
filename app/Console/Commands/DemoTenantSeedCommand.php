<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\Tenant\TenantDemoSeeder;
use Illuminate\Console\Command;
use Throwable;

class DemoTenantSeedCommand extends Command
{
    protected $signature = 'demo:tenant-seed {tenantSlug : Tenant workspace slug}';

    protected $description = 'Seed realistic demo data into a tenant database';

    public function __construct(
        private readonly TenantDatabaseManager $tenantDatabaseManager,
        private readonly TenantDemoSeeder $tenantDemoSeeder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantSlug = trim((string) $this->argument('tenantSlug'));
        if ($tenantSlug === '') {
            $this->error('tenantSlug is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->first();
        if (! $tenant instanceof Tenant) {
            $this->error("Tenant [{$tenantSlug}] not found.");

            return self::FAILURE;
        }

        try {
            $this->tenantDatabaseManager->connect($tenant);
            $this->tenantDatabaseManager->runTenantSeeders();

            $summary = $this->tenantDemoSeeder->runForTenant($tenant);
        } catch (Throwable $exception) {
            $this->error('Demo tenant seed failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($summary as $metric => $value) {
            $rows[] = [
                'metric' => $metric,
                'value' => is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
            ];
        }

        $this->info('Demo tenant seed completed successfully.');
        $this->line('Idempotent mode: demo records are upserted by deterministic keys and existing tenant data is not deleted.');
        $this->table(['Metric', 'Value'], $rows);

        return self::SUCCESS;
    }
}
