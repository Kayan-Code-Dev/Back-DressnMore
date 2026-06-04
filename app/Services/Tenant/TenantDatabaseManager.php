<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Database\Seeders\Tenant\TenantSettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class TenantDatabaseManager
{
    public function connect(Tenant $tenant): void
    {
        $databaseName = $tenant->database_name;

        if (! is_string($databaseName) || trim($databaseName) === '') {
            throw new RuntimeException('Tenant database is not configured.');
        }

        $connectionDatabase = $this->tenantDriver() === 'sqlite'
            ? $this->resolveSqlitePath($databaseName)
            : $databaseName;

        Config::set('database.connections.tenant.database', $connectionDatabase);

        DB::purge('tenant');
        DB::reconnect('tenant');

        try {
            $this->testConnection();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to tenant database.', 0, $exception);
        }
    }

    public function createDatabase(string $databaseName): void
    {
        $driver = $this->tenantDriver();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $identifier = $this->quoteMysqlIdentifier($databaseName);
            DB::connection('central')->statement(
                "CREATE DATABASE IF NOT EXISTS {$identifier} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            return;
        }

        if ($driver === 'sqlite') {
            $path = $this->resolveSqlitePath($databaseName);
            $directory = dirname($path);
            if (! is_dir($directory)) {
                File::makeDirectory($directory, 0775, true);
            }

            if (! File::exists($path)) {
                File::put($path, '');
            }

            return;
        }

        throw new RuntimeException("Unsupported tenant driver [{$driver}] for database creation.");
    }

    public function databaseExists(string $databaseName): bool
    {
        $driver = $this->tenantDriver();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = DB::connection('central')
                ->selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            return File::exists($this->resolveSqlitePath($databaseName));
        }

        throw new RuntimeException("Unsupported tenant driver [{$driver}] for existence check.");
    }

    public function dropDatabase(string $databaseName): void
    {
        $driver = $this->tenantDriver();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::connection('central')->statement('DROP DATABASE IF EXISTS '.$this->quoteMysqlIdentifier($databaseName));

            return;
        }

        if ($driver === 'sqlite') {
            $path = $this->resolveSqlitePath($databaseName);
            if (File::exists($path)) {
                File::delete($path);
            }

            return;
        }

        throw new RuntimeException("Unsupported tenant driver [{$driver}] for database drop.");
    }

    public function runTenantMigrations(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Tenant migrations failed: '.trim(Artisan::output()));
        }
    }

    public function runTenantSeeders(): void
    {
        $seeders = [
            TenantRolePermissionSeeder::class,
            TenantSettingsSeeder::class,
        ];

        foreach ($seeders as $seederClass) {
            $exitCode = Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => $seederClass,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException("Tenant seeder [{$seederClass}] failed: ".trim(Artisan::output()));
            }
        }
    }

    public function testConnection(): bool
    {
        DB::connection('tenant')->select('SELECT 1');

        return true;
    }

    private function tenantDriver(): string
    {
        return (string) Config::get('database.connections.tenant.driver', 'mysql');
    }

    private function quoteMysqlIdentifier(string $databaseName): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
            throw new RuntimeException('Unsafe tenant database identifier.');
        }

        return '`'.str_replace('`', '``', $databaseName).'`';
    }

    private function resolveSqlitePath(string $databaseName): string
    {
        if ($databaseName === ':memory:') {
            return $databaseName;
        }

        if (str_starts_with($databaseName, '/')) {
            $allowedRoots = [storage_path('framework/tenants')];
            if (app()->environment('testing')) {
                $allowedRoots[] = storage_path('framework/testing');
                $allowedRoots[] = database_path();
            }

            foreach ($allowedRoots as $allowedRoot) {
                if ($this->pathIsWithin($databaseName, $allowedRoot)) {
                    return $databaseName;
                }
            }

            throw new RuntimeException('Unsafe tenant SQLite path.');
        }

        if (str_contains($databaseName, '/') || str_contains($databaseName, '\\')) {
            throw new RuntimeException('Unsafe tenant SQLite path.');
        }

        return storage_path('framework/tenants/'.$databaseName);
    }

    private function pathIsWithin(string $path, string $root): bool
    {
        $rootRealPath = realpath($root);
        if ($rootRealPath === false) {
            File::makeDirectory($root, 0775, true, true);
            $rootRealPath = realpath($root);
            if ($rootRealPath === false) {
                return false;
            }
        }

        $pathRealPath = realpath($path);
        if ($pathRealPath === false) {
            $directoryRealPath = realpath(dirname($path));
            if ($directoryRealPath === false) {
                return false;
            }

            $pathRealPath = $directoryRealPath.'/'.basename($path);
        }

        $normalizedPath = rtrim(str_replace('\\', '/', $pathRealPath), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $rootRealPath), '/');

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot.'/');
    }
}
