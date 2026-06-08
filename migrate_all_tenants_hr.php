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
        $results[$tenant->slug] = substr($e->getMessage(), 0, 200);
    }
}
echo json_encode($results, JSON_PRETTY_PRINT)."\n";
$t = App\Models\Central\Tenant::where('slug', 'phase1-qa')->first();
if ($t) {
    app(App\Services\Tenant\TenantDatabaseManager::class)->connect($t);
    $tables = ['hr_departments','hr_job_titles','hr_employees','hr_documents','hr_settings'];
    $found = [];
    foreach ($tables as $table) {
        $found[$table] = Illuminate\Support\Facades\Schema::connection('tenant')->hasTable($table);
    }
    echo "TABLES:".json_encode($found)."\n";
    $keys = ['hr.view','hr.dashboard.view','hr.employees.view','hr.employees.create','hr.employees.update','hr.employees.delete','hr.employees.status','hr.documents.view','hr.documents.upload','hr.documents.delete','hr.settings.view','hr.settings.update','hr.departments.view','hr.departments.create','hr.departments.update','hr.departments.delete','hr.job_titles.view','hr.job_titles.create','hr.job_titles.update','hr.job_titles.delete'];
    $owner = App\Models\Tenant\Role::where('slug','owner')->first();
    $ownerCount = $owner ? $owner->permissions()->whereIn('key', $keys)->count() : 0;
    echo "OWNER_HR_PERMS:$ownerCount\n";
}
