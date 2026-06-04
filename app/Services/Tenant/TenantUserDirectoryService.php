<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use Illuminate\Validation\ValidationException;

class TenantUserDirectoryService
{
    public function register(Tenant $tenant, string $email): void
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return;
        }

        $existing = TenantUserDirectory::query()
            ->where('email', $normalizedEmail)
            ->first();

        if ($existing instanceof TenantUserDirectory && (int) $existing->tenant_id !== (int) $tenant->id) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered for another tenant.'],
            ]);
        }

        TenantUserDirectory::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $normalizedEmail,
            ],
            ['status' => 'active'],
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

        return null;
    }

    public function emailBelongsToTenant(Tenant $tenant, string $email): bool
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return false;
        }

        return TenantUserDirectory::query()
            ->where('email', $normalizedEmail)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->exists();
    }
}
