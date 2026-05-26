<?php

namespace App\Services\Platform;

use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Services\Tenant\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TenantProvisioningService
{
    public function __construct(private readonly TenantDatabaseManager $tenantDatabaseManager) {}

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Tenant::query()
            ->with('plan')
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(database_name) LIKE ?', [$wildcard]);
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $planId = $filters['plan_id'] ?? null;
        if ($planId !== null && trim((string) $planId) !== '') {
            $query->where('plan_id', (int) $planId);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function provision(array $data): Tenant
    {
        $name = trim((string) $data['name']);
        $slug = $this->resolveSlug($data['slug'] ?? $name);
        $databaseName = $this->resolveDatabaseName(
            provided: $data['database_name'] ?? null,
            slug: $slug,
        );

        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'database_name' => $databaseName,
            'status' => 'provisioning',
            'plan_id' => $data['plan_id'] ?? null,
            'subscription_starts_at' => $data['subscription_starts_at'] ?? CarbonImmutable::now(),
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->log($tenant, 'tenant_created', 'success', 'Tenant record created');

        try {
            if (! $this->tenantDatabaseManager->databaseExists($databaseName)) {
                $this->tenantDatabaseManager->createDatabase($databaseName);
                $this->log($tenant, 'database_created', 'success', 'Tenant database created', [
                    'database_name' => $databaseName,
                ]);
            } else {
                $this->log($tenant, 'database_exists', 'success', 'Tenant database already exists', [
                    'database_name' => $databaseName,
                ]);
            }

            $this->tenantDatabaseManager->connect($tenant);
            $this->tenantDatabaseManager->runTenantMigrations();
            $this->tenantDatabaseManager->runTenantSeeders();
            $this->tenantDatabaseManager->testConnection();

            $tenant->status = 'active';
            $tenant->save();

            $this->log($tenant, 'provisioning_completed', 'success', 'Tenant provisioning completed');

            return $tenant->refresh()->load('plan');
        } catch (Throwable $exception) {
            $tenant->status = 'provisioning_failed';
            $tenant->save();

            $this->log($tenant, 'provisioning_failed', 'failed', $exception->getMessage(), [
                'database_name' => $databaseName,
            ]);

            throw new RuntimeException('Tenant provisioning failed.', 0, $exception);
        }
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $tenant->status = 'suspended';
        $tenant->save();

        return $tenant->refresh()->load('plan');
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->status = 'active';
        $tenant->save();

        return $tenant->refresh()->load('plan');
    }

    public function renew(Tenant $tenant, array $data): Tenant
    {
        $days = (int) ($data['days'] ?? 30);

        if (isset($data['subscription_ends_at'])) {
            $subscriptionEndsAt = CarbonImmutable::parse((string) $data['subscription_ends_at']);
        } else {
            $base = $tenant->subscription_ends_at !== null
                ? CarbonImmutable::parse((string) $tenant->subscription_ends_at)
                : CarbonImmutable::now();

            if ($base->lt(CarbonImmutable::now())) {
                $base = CarbonImmutable::now();
            }

            $subscriptionEndsAt = $base->addDays($days);
        }

        if ($tenant->subscription_starts_at === null) {
            $tenant->subscription_starts_at = CarbonImmutable::now();
        }

        $tenant->subscription_ends_at = $subscriptionEndsAt;
        $tenant->status = 'active';
        $tenant->save();

        $this->log($tenant, 'subscription_renewed', 'success', 'Tenant subscription renewed', [
            'subscription_ends_at' => $subscriptionEndsAt->toISOString(),
        ]);

        return $tenant->refresh()->load('plan');
    }

    private function resolveSlug(mixed $slugInput): string
    {
        $baseSlug = Str::slug(trim((string) $slugInput));
        if ($baseSlug === '') {
            $baseSlug = 'tenant';
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function resolveDatabaseName(mixed $provided, string $slug): string
    {
        $driver = (string) Config::get('database.connections.tenant.driver', 'mysql');
        $providedName = trim((string) $provided);

        if ($driver === 'sqlite') {
            if ($providedName !== '') {
                return $this->sqliteDatabasePath($providedName);
            }

            $filename = $slug.'.sqlite';

            return storage_path('framework/tenants/'.$filename);
        }

        if ($providedName !== '') {
            return $providedName;
        }

        $prefix = (string) config('tenancy.provisioning.database_prefix', 'tenant_');
        $suffix = (string) config('tenancy.provisioning.database_suffix', '');
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', $prefix.$slug.$suffix) ?: 'tenant_db';

        $candidate = $base;
        $counter = 2;

        while (Tenant::query()->where('database_name', $candidate)->exists()) {
            $candidate = "{$base}_{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function sqliteDatabasePath(string $providedName): string
    {
        if ($providedName === ':memory:') {
            return $providedName;
        }

        if (str_starts_with($providedName, '/')) {
            return $providedName;
        }

        $filename = str_ends_with($providedName, '.sqlite') ? $providedName : $providedName.'.sqlite';

        return storage_path('framework/tenants/'.$filename);
    }

    private function log(
        ?Tenant $tenant,
        string $step,
        string $status,
        ?string $message = null,
        array $context = []
    ): void {
        TenantProvisioningLog::query()->create([
            'tenant_id' => $tenant?->id,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
