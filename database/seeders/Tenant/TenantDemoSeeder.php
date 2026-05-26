<?php

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Services\Tenant\TenantDemoSeedService;
use Illuminate\Database\Seeder;
use RuntimeException;

class TenantDemoSeeder extends Seeder
{
    public function __construct(private readonly TenantDemoSeedService $tenantDemoSeedService) {}

    public function run(): void
    {
        throw new RuntimeException('TenantDemoSeeder requires a tenant instance. Use the demo:tenant-seed command.');
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function runForTenant(Tenant $tenant): array
    {
        return $this->tenantDemoSeedService->seed($tenant);
    }
}
