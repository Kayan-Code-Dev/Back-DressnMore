<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

        Config::set('database.connections.tenant.database', $databaseName);

        DB::purge('tenant');
        DB::reconnect('tenant');

        try {
            DB::connection('tenant')->select('SELECT 1');
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to tenant database.', 0, $exception);
        }
    }
}
