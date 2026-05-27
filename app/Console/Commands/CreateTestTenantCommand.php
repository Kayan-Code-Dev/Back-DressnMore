<?php

namespace App\Console\Commands;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class CreateTestTenantCommand extends Command
{
    protected $signature = 'tenant:create-test
        {--slug=smoke-test : Workspace slug}
        {--name=Smoke Test Atelier : Tenant display name}
        {--email=smoke@test.local : Admin user email}
        {--plan=basic : Plan slug to assign}';

    protected $description = 'Create a test tenant and admin user for frontend smoke testing (local/staging only)';

    public function __construct(private readonly TenantDatabaseManager $tenantDatabaseManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->isAllowedEnvironment()) {
            $this->error('This command is restricted to local, staging, and testing environments.');

            return self::FAILURE;
        }

        $slug = Str::slug(trim((string) $this->option('slug')));
        $name = trim((string) $this->option('name'));
        $email = strtolower(trim((string) $this->option('email')));
        $planSlug = trim((string) $this->option('plan'));

        if ($slug === '' || $name === '' || $email === '') {
            $this->error('Slug, name, and email are required.');

            return self::FAILURE;
        }

        $databaseName = (string) config('tenancy.provisioning.database_prefix', 'tenant_').$slug;

        $tenantCreated = false;
        $databaseCreated = false;
        $migrated = false;
        $seeded = false;
        $userCreated = false;
        $password = null;

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant instanceof Tenant) {
            $this->info("Tenant [{$slug}] already exists (status: {$tenant->status}).");

            if ($tenant->status !== 'active') {
                $this->warn("Tenant status is [{$tenant->status}]. Attempting to re-provision.");
            }

            $databaseName = $tenant->database_name;
        } else {
            $plan = $planSlug !== '' ? Plan::query()->where('slug', $planSlug)->first() : null;

            $tenant = Tenant::query()->create([
                'name' => $name,
                'slug' => $slug,
                'database_name' => $databaseName,
                'status' => 'provisioning',
                'plan_id' => $plan?->id,
                'subscription_starts_at' => now(),
                'subscription_ends_at' => now()->addYear(),
            ]);

            $tenantCreated = true;
            $this->log($tenant, 'tenant_created', 'success', 'Test tenant record created');
            $this->info("Tenant [{$slug}] created.");
        }

        try {
            if (! $this->tenantDatabaseManager->databaseExists($databaseName)) {
                $this->tenantDatabaseManager->createDatabase($databaseName);
                $databaseCreated = true;
                $this->log($tenant, 'database_created', 'success', 'Test tenant database created', [
                    'database_name' => $databaseName,
                ]);
                $this->info("Database [{$databaseName}] created.");
            } else {
                $this->info("Database [{$databaseName}] already exists.");
            }

            $this->tenantDatabaseManager->connect($tenant);
            $this->info('Connected to tenant database.');

            $this->tenantDatabaseManager->runTenantMigrations();
            $migrated = true;
            $this->info('Tenant migrations applied.');

            $this->tenantDatabaseManager->runTenantSeeders();
            $seeded = true;
            $this->info('Tenant seeders applied (roles, permissions, settings).');

            $this->tenantDatabaseManager->testConnection();
        } catch (Throwable $exception) {
            $tenant->status = 'provisioning_failed';
            $tenant->save();
            $this->log($tenant, 'provisioning_failed', 'failed', $exception->getMessage());
            $this->error('Provisioning failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $tenant->status = 'active';
        $tenant->save();

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser instanceof User) {
            $this->info("Admin user [{$email}] already exists.");

            $ownerRole = Role::query()->where('slug', 'owner')->first();
            if ($ownerRole) {
                $existingUser->roles()->syncWithoutDetaching([$ownerRole->id]);
            }
        } else {
            $password = Str::random(16);

            $newUser = User::query()->create([
                'name' => 'Smoke Test Admin',
                'email' => $email,
                'password' => $password,
                'phone' => null,
                'status' => 'active',
            ]);

            $ownerRole = Role::query()->where('slug', 'owner')->first();
            if ($ownerRole) {
                $newUser->roles()->syncWithoutDetaching([$ownerRole->id]);
            }

            $userCreated = true;
            $this->log($tenant, 'admin_user_created', 'success', 'Test admin user created');
            $this->info("Admin user [{$email}] created with Owner role.");
        }

        $this->log($tenant, 'test_tenant_ready', 'success', 'Test tenant provisioning completed');

        $this->newLine();
        $this->info('Test tenant is ready.');
        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['Workspace slug', $slug],
                ['Tenant name', $name],
                ['Database', $databaseName],
                ['Tenant record', $tenantCreated ? 'Created' : 'Reused (already existed)'],
                ['Database', $databaseCreated ? 'Created' : 'Already existed'],
                ['Migrations', $migrated ? 'Applied' : 'Skipped'],
                ['Seeders', $seeded ? 'Applied' : 'Skipped'],
                ['Admin email', $email],
                ['Admin user', $userCreated ? 'Created' : 'Reused (already existed)'],
                ['Admin password', $password !== null ? $password : '(unchanged — user already existed)'],
            ]
        );

        if ($password !== null) {
            $this->newLine();
            $this->warn('Save the password above — it will not be shown again.');
        }

        $this->newLine();
        $this->info('Login:');
        $this->line('  POST /api/tenant/auth/login');
        $this->line("  Header: X-Tenant: {$slug}");
        $this->line("  Body:   {\"email\":\"{$email}\",\"password\":\"<password>\"}");
        $this->newLine();
        $this->info('Verify:');
        $this->line("  php artisan tenant:health {$slug}");

        return self::SUCCESS;
    }

    private function isAllowedEnvironment(): bool
    {
        $env = strtolower((string) app()->environment());

        return in_array($env, ['local', 'staging', 'testing'], true);
    }

    private function log(
        Tenant $tenant,
        string $step,
        string $status,
        ?string $message = null,
        array $context = []
    ): void {
        TenantProvisioningLog::query()->create([
            'tenant_id' => $tenant->id,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
