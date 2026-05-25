<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantHealthCommand extends Command
{
    protected $signature = 'tenant:health {slug}';

    protected $description = 'Check tenant health status';

    public function __construct(
        private readonly TenantDatabaseManager $tenantDatabaseManager,
        private readonly SubscriptionService $subscriptionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');
        $failed = false;

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            $this->line('FAIL: Tenant not found');
            return self::FAILURE;
        }
        $this->line('PASS: Tenant found');

        if ($tenant->status !== 'active') {
            $this->line("FAIL: Tenant status is {$tenant->status}");
            $failed = true;
        } else {
            $this->line('PASS: Tenant status is active');
        }

        $subscription = $this->subscriptionService->activeForTenant($tenant);
        if (! $subscription) {
            $this->line('FAIL: No active subscription');
            $failed = true;
        } else {
            $this->line('PASS: Active subscription found');
        }

        try {
            $this->tenantDatabaseManager->connect($tenant);
            $this->line('PASS: Tenant database connection is healthy');
        } catch (Throwable $exception) {
            $this->line('FAIL: Tenant database connection failed');
            return self::FAILURE;
        }

        $requiredTables = [
            'users',
            'roles',
            'permissions',
            'settings',
            'invoices',
            'dresses',
            'customers',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                $this->line("FAIL: Missing table {$table}");
                $failed = true;
            } else {
                $this->line("PASS: Table {$table} exists");
            }
        }

        $ownerExists = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($tenant->owner_email)])
            ->exists();

        if (! $ownerExists) {
            $this->line('FAIL: Owner/admin user does not exist');
            $failed = true;
        } else {
            $this->line('PASS: Owner/admin user exists');
        }

        $defaultSettingExists = Setting::query()
            ->where('key', 'company.name')
            ->exists();

        if (! $defaultSettingExists) {
            $this->line('FAIL: Default settings are missing');
            $failed = true;
        } else {
            $this->line('PASS: Default settings exist');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
