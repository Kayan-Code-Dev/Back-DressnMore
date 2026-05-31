<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\Tenant\AccountSeeder;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncTenantAccountingCommand extends Command
{
    protected $signature = 'tenants:sync-accounting {--tenant= : Tenant slug to sync only}';

    protected $description = 'Run tenant accounting migrations and seed default accounts/permissions';

    public function handle(TenantDatabaseManager $tenantDatabaseManager): int
    {
        $slug = $this->option('tenant');
        $tenants = Tenant::query()
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->where('status', 'active')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found to sync.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Syncing tenant: {$tenant->slug}");
            $tenantDatabaseManager->connect($tenant);
            $tenantDatabaseManager->runTenantMigrations();

            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => TenantRolePermissionSeeder::class,
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => AccountSeeder::class,
                '--force' => true,
            ]);

            $this->line(trim(Artisan::output()));
        }

        $this->info('Tenant accounting sync completed.');

        return self::SUCCESS;
    }
}
