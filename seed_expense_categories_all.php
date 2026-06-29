#!/usr/bin/env php
<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\Tenant\ExpenseCategorySeeder;

$ctx = app(TenantContext::class);
$db = app(TenantDatabaseManager::class);
$seeder = new ExpenseCategorySeeder();

foreach (Tenant::query()->whereIn("status", ["active", "provisioning"])->get() as $tenant) {
    echo "Seeding categories for {$tenant->slug}\n";
    $ctx->setTenant($tenant);
    $db->connect($tenant);
    $seeder->run();
}
echo "DONE\n";