<?php

namespace App\Console\Commands;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantUserDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class CreateTestTenantCommand extends Command
{
    protected $signature = 'tenant:create-test
        {--slug=smoke-test : Workspace slug}
        {--name=Smoke Test Atelier : Tenant display name}
        {--email=smoke@test.local : Admin user email}
        {--admin-name=Smoke Test Admin : Admin user display name}
        {--password= : Admin password (min 8 chars); random if omitted for new users}
        {--reset-password : Reset admin password when the user already exists}
        {--plan=basic : Plan slug to assign}';

    protected $description = 'Create a test tenant and admin user for frontend smoke testing (local/staging only)';

    public function __construct(
        private readonly TenantDatabaseManager $tenantDatabaseManager,
        private readonly TenantUserDirectoryService $tenantUserDirectoryService,
    )
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
        $adminName = trim((string) $this->option('admin-name'));
        $providedPassword = $this->option('password');
        $providedPassword = is_string($providedPassword) ? trim($providedPassword) : '';
        $resetPassword = (bool) $this->option('reset-password');
        $planSlug = trim((string) $this->option('plan'));

        if ($slug === '' || $name === '' || $email === '' || $adminName === '') {
            $this->error('Slug, name, admin name, and email are required.');

            return self::FAILURE;
        }

        if ($providedPassword !== '' && strlen($providedPassword) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        if ($resetPassword && $providedPassword === '') {
            $this->error('The --reset-password option requires --password.');

            return self::FAILURE;
        }

        $prefix = (string) config('tenancy.provisioning.database_prefix', 'tenant_');
        $suffix = (string) config('tenancy.provisioning.database_suffix', '');
        $databaseName = preg_replace('/[^A-Za-z0-9_]/', '_', $prefix.$slug.$suffix) ?: 'tenant_db';

        $tenantCreated = false;
        $databaseCreated = false;
        $migrated = false;
        $seeded = false;
        $userCreated = false;
        $passwordReset = false;
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

            if ($resetPassword) {
                $password = $providedPassword;
                $existingUser->forceFill([
                    'name' => $adminName,
                    'password' => Hash::make($password),
                    'status' => 'active',
                ])->save();

                $passwordReset = true;
                $this->log($tenant, 'admin_password_reset', 'success', 'Test admin password reset');
                $this->info("Admin user [{$email}] password reset.");
            }
        } else {
            $password = $providedPassword !== '' ? $providedPassword : Str::random(16);

            $newUser = User::query()->create([
                'name' => $adminName,
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

        $this->tenantUserDirectoryService->register($tenant, $email);

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
                ['Admin user', $userCreated ? 'Created' : ($passwordReset ? 'Reused (password reset)' : 'Reused (already existed)')],
                ['Admin password', $password !== null ? $password : '(unchanged — user already existed)'],
            ]
        );

        if ($password !== null && $providedPassword === '') {
            $this->newLine();
            $this->warn('Save the password above — it will not be shown again.');
        }

        $this->newLine();
        $this->info('Login:');
        $this->line('  POST /api/tenant/login');
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
