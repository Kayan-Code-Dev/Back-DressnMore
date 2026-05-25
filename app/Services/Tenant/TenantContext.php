<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;

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
}
