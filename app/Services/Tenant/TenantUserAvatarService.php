<?php

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TenantUserAvatarService
{
    private const DISK = 'public';

    public function store(Tenant $tenant, User $user, UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = $this->directoryFor($tenant, $user);

        Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        return $directory.'/'.$filename;
    }

    public function deleteIfOwned(Tenant $tenant, ?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        if (! $this->pathBelongsToTenant($tenant, $path)) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path, ?Tenant $tenant): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if ($tenant !== null && ! $this->pathBelongsToTenant($tenant, $path) && ! $this->isLegacyPath($path)) {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    public function pathBelongsToTenant(Tenant $tenant, string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return str_starts_with($normalized, 'tenants/'.$tenant->id.'/');
    }

    private function directoryFor(Tenant $tenant, User $user): string
    {
        return 'tenants/'.$tenant->id.'/users/'.$user->id.'/avatar';
    }

    private function isLegacyPath(string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return ! str_starts_with($normalized, 'tenants/');
    }

    public function assertTenantContext(Tenant $tenant): void
    {
        if ($tenant->id <= 0) {
            throw new RuntimeException('Tenant context is required for avatar storage.');
        }
    }
}
