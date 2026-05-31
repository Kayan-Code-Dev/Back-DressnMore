<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;

class TenantUserDirectoryService
{
    public function register(Tenant $tenant, string $email): void
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return;
        }

        TenantUserDirectory::query()->updateOrCreate(
            ['email' => $normalizedEmail],
            [
                'tenant_id' => $tenant->id,
                'status' => 'active',
            ],
        );
    }

    public function removeForTenant(Tenant $tenant): void
    {
        TenantUserDirectory::query()
            ->where('tenant_id', $tenant->id)
            ->delete();
    }

    public function findTenantByEmail(string $email): ?Tenant
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return null;
        }

        $entry = TenantUserDirectory::query()
            ->with('tenant.plan')
            ->where('email', $normalizedEmail)
            ->where('status', 'active')
            ->first();

        if ($entry?->tenant instanceof Tenant) {
            return $entry->tenant;
        }

        return Tenant::query()
            ->with('plan')
            ->where('status', 'active')
            ->get()
            ->first(function (Tenant $tenant) use ($normalizedEmail): bool {
                $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
                $adminEmail = strtolower(trim((string) ($metadata['admin_email'] ?? '')));

                return $adminEmail !== '' && $adminEmail === $normalizedEmail;
            });
    }
}
