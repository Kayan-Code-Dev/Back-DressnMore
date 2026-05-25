<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\Role;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantHealthCommand extends Command
{
    protected $signature = 'tenant:health {slug}';

    protected $description = 'Check tenant health status by slug';

    public function __construct(private readonly TenantDatabaseManager $tenantDatabaseManager)
    {
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

        if ($tenant->subscription_ends_at !== null && now()->greaterThan($tenant->subscription_ends_at)) {
            $this->line('FAIL: Subscription date is expired');
            $failed = true;
        } else {
            $this->line('PASS: Subscription date is valid');
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
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                $this->line("FAIL: Missing table {$table}");
                $failed = true;
            } else {
                $this->line("PASS: Table {$table} exists");
            }
        }

        if (Schema::connection('tenant')->hasTable('users') && Schema::connection('tenant')->hasTable('roles')) {
            $ownerRole = Role::query()->where('slug', 'owner')->first();
            $ownerExists = $ownerRole !== null
                ? User::query()->whereHas('roles', fn ($query) => $query->where('roles.id', $ownerRole->id))->exists()
                : false;

            if (! $ownerExists) {
                $this->line('FAIL: Owner user does not exist');
                $failed = true;
            } else {
                $this->line('PASS: Owner user exists');
            }
        }

        if (Schema::connection('tenant')->hasTable('settings')) {
            $defaultSettingExists = Setting::query()
                ->where('key', 'app.settings')
                ->exists();

            if (! $defaultSettingExists) {
                $this->line('FAIL: Default settings are missing');
                $failed = true;
            } else {
                $this->line('PASS: Default settings exist');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
