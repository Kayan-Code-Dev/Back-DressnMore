<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantUserDirectoryService;
use Illuminate\Console\Command;

class SyncTenantUserDirectoryCommand extends Command
{
    protected $signature = 'tenant:sync-user-directory';

    protected $description = 'Backfill central tenant_user_directory from tenant metadata admin_email';

    public function __construct(private readonly TenantUserDirectoryService $tenantUserDirectoryService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use (&$count): void {
            $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
            $email = trim((string) ($metadata['admin_email'] ?? ''));

            if ($email === '') {
                return;
            }

            $this->tenantUserDirectoryService->register($tenant, $email);
            $count++;
            $this->line("Registered [{$email}] for tenant [{$tenant->slug}]");
        });

        $this->info("Synced {$count} tenant admin email(s).");

        return self::SUCCESS;
    }
}
