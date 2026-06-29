<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(App\Services\Platform\TenantProvisioningService::class);
$results = [];
foreach (App\Models\Central\Tenant::query()->where('status', 'active')->get() as $tenant) {
    try {
        $svc->migrate($tenant);
        $results[$tenant->slug] = 'ok';
    } catch (Throwable $e) {
        $results[$tenant->slug] = substr($e->getMessage(), 0, 300);
    }
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
