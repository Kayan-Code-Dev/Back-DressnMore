<?php

namespace App\Services\Tenant;

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        private readonly TenantDatabaseManager $tenantDatabaseManager
    ) {
    }

    public function provision(array $data): array
    {
        $tenant = null;

        try {
            $tenant = DB::connection('central')->transaction(function () use ($data) {
                $existing = Tenant::query()
                    ->where('slug', $data['slug'])
                    ->exists();

                if ($existing) {
                    throw new RuntimeException('Tenant slug already exists.');
                }

                $tenant = Tenant::query()->create([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'status' => 'provisioning',
                    'owner_name' => $data['owner_name'],
                    'owner_email' => $data['owner_email'],
                    'database_name' => $this->generateDatabaseName($data['slug']),
                ]);

                $this->logProvisioning($tenant, 'info', 'Tenant provisioning started');

                return $tenant;
            });

            if (! $tenant instanceof Tenant) {
                throw new RuntimeException('Tenant record could not be created.');
            }

            $this->createTenantDatabase($tenant->database_name);
            $this->tenantDatabaseManager->connect($tenant);

            $this->runTenantMigrations();
            $this->seedTenantDefaults($tenant, $data);
            $this->createCentralSubscription($tenant, $data);

            $tenant->update(['status' => 'active']);
            $this->logProvisioning($tenant, 'info', 'Tenant provisioning completed successfully');

            return [
                'tenant' => $tenant->fresh(),
                'workspace' => $tenant->slug,
                'owner_email' => $data['owner_email'],
            ];
        } catch (Throwable $exception) {
            if ($tenant instanceof Tenant) {
                $tenant->update(['status' => 'provisioning_failed']);
                $this->logProvisioning($tenant, 'error', $exception->getMessage(), [
                    'trace' => $exception->getTraceAsString(),
                ]);
            }

            Log::error('tenant.provisioning.failed', [
                'tenant_id' => $tenant?->id,
                'tenant_slug' => $tenant?->slug,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function createCentralSubscription(Tenant $tenant, array $data): void
    {
        $plan = Plan::query()->findOrFail($data['plan_id']);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $data['subscription_starts_at'],
            'ends_at' => $data['subscription_ends_at'],
        ]);
    }

    private function seedTenantDefaults(Tenant $tenant, array $data): void
    {
        $ownerRole = Role::query()->firstOrCreate(
            ['name' => 'owner'],
            ['display_name' => 'Owner']
        );

        $defaultPermissions = [
            'dashboard.view',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'dresses.view',
            'dresses.create',
            'customers.view',
            'customers.create',
            'employees.manage',
            'suppliers.manage',
            'accounting.view',
            'reports.view',
            'settings.manage',
        ];

        $permissionIds = [];
        foreach ($defaultPermissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => Str::headline(str_replace('.', ' ', $permissionName))]
            );
            $permissionIds[] = $permission->id;
        }

        $ownerRole->permissions()->sync($permissionIds);

        $owner = User::query()->create([
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => Hash::make($data['owner_password']),
            'is_active' => true,
        ]);

        $owner->roles()->sync([$ownerRole->id]);

        Setting::query()->firstOrCreate(
            ['key' => 'company.name'],
            ['value' => $tenant->name]
        );
    }

    private function runTenantMigrations(): void
    {
        $migrationPath = database_path('migrations/tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => $migrationPath,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function createTenantDatabase(string $databaseName): void
    {
        $quotedName = str_replace('`', '``', $databaseName);
        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$quotedName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function generateDatabaseName(string $slug): string
    {
        $prefix = (string) config('tenancy.provisioning.database_prefix', 'tenant_');
        $suffix = (string) config('tenancy.provisioning.database_suffix', '');
        $cleanSlug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $slug) ?: $slug);

        return "{$prefix}{$cleanSlug}{$suffix}";
    }

    private function logProvisioning(Tenant $tenant, string $level, string $message, array $context = []): void
    {
        TenantProvisioningLog::query()->create([
            'tenant_id' => $tenant->id,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
