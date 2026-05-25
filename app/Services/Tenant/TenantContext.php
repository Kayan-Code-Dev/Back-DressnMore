<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use RuntimeException;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function requireTenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException('Tenant has not been resolved.');
        }

        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function slug(): ?string
    {
        return $this->tenant?->slug;
    }

    public function databaseName(): ?string
    {
        return $this->tenant?->database_name;
    }

    public function isResolved(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
