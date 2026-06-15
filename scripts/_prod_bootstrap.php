<?php
/**
 * Bootstrap production clean build: plans, platform admin, first tenant.
 * Passwords written only to secrets file — never echoed.
 */
declare(strict_types=1);

$base = '/var/www/dressnmore-production/backend';
chdir($base);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Services\Platform\TenantProvisioningService;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\Central\PlanFeatureSeeder;
use Database\Seeders\Central\PlanSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

$secretsPath = '/root/.dressnmore-production-bootstrap.secrets';

function strongPassword(): string
{
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', 'Aa'), '=');
}

function loadOrCreateSecrets(string $path): array
{
    if (is_readable($path)) {
        $parsed = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $parsed[trim($k)] = trim($v);
            }
        }
        if (! empty($parsed['PLATFORM_ADMIN_PASSWORD']) && ! empty($parsed['TENANT_OWNER_PASSWORD'])) {
            return $parsed;
        }
    }

    $secrets = [
        'PLATFORM_ADMIN_EMAIL' => 'admin@dressnmore.it.com',
        'PLATFORM_ADMIN_NAME' => 'DressnMore Platform Admin',
        'PLATFORM_ADMIN_PASSWORD' => strongPassword(),
        'TENANT_NAME' => 'الحاطوم للأزياء',
        'TENANT_SLUG' => 'alhatom',
        'TENANT_OWNER_EMAIL' => 'owner@alhatom.test',
        'TENANT_OWNER_NAME' => 'Alhatom Owner',
        'TENANT_OWNER_PASSWORD' => strongPassword(),
        'TENANT_PLAN' => 'pro',
    ];

    $lines = [];
    foreach ($secrets as $k => $v) {
        $lines[] = $k.'='.$v;
    }
    file_put_contents($path, implode("\n", $lines)."\n");
    chmod($path, 0600);

    return $secrets;
}

$secrets = loadOrCreateSecrets($secretsPath);

Artisan::call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PlanFeatureSeeder::class, '--force' => true]);

SuperAdmin::query()->updateOrCreate(
    ['email' => strtolower($secrets['PLATFORM_ADMIN_EMAIL'])],
    [
        'name' => $secrets['PLATFORM_ADMIN_NAME'],
        'password' => Hash::make($secrets['PLATFORM_ADMIN_PASSWORD']),
        'status' => 'active',
    ]
);

SuperAdmin::query()
    ->where('email', '!=', strtolower($secrets['PLATFORM_ADMIN_EMAIL']))
    ->delete();

$plan = Plan::query()->where('slug', $secrets['TENANT_PLAN'])->firstOrFail();
$provisioning = app(TenantProvisioningService::class);

$tenant = Tenant::query()->where('slug', $secrets['TENANT_SLUG'])->first();
if ($tenant === null) {
    $tenant = $provisioning->provision([
        'name' => $secrets['TENANT_NAME'],
        'slug' => $secrets['TENANT_SLUG'],
        'plan_id' => $plan->id,
    ]);
} elseif ($tenant->status !== 'active') {
    $tenant = $provisioning->migrate($tenant);
}

$provisioning->seedAdmin($tenant, [
    'admin_email' => $secrets['TENANT_OWNER_EMAIL'],
    'admin_password' => $secrets['TENANT_OWNER_PASSWORD'],
    'admin_name' => $secrets['TENANT_OWNER_NAME'],
]);

$tenantDb = app(TenantDatabaseManager::class);
$tenantDb->connect($tenant->refresh());
$tenantDb->runTenantMigrations();

$tenant->refresh()->load('plan');

$result = [
    'ok' => true,
    'secrets_file' => $secretsPath,
    'platform_admin_email' => $secrets['PLATFORM_ADMIN_EMAIL'],
    'tenant' => [
        'id' => $tenant->id,
        'name' => $tenant->name,
        'slug' => $tenant->slug,
        'database_name' => $tenant->database_name,
        'status' => $tenant->status,
        'plan' => $tenant->plan?->slug,
        'owner_email' => $secrets['TENANT_OWNER_EMAIL'],
    ],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
