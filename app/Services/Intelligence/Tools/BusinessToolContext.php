<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Immutable execution context for every business tool.
 *
 * Enforces:
 * - Tenant isolation (database prefix via BaseTenantModel)
 * - Permission gating (role-based via User->roles->permissions)
 * - Branch scoping (branch_id filter for non-admin users)
 * - Date range normalization (timezone-aware)
 * - Read-only safety (no writes allowed through this path)
 */
final class BusinessToolContext
{
    private ?array $cachedBranchIds = null;
    private ?array $cachedPermissions = null;

    public function __construct(
        private readonly string $tenantSlug,
        private readonly User $user,
        private readonly ?int $branchId = null,
        private readonly ?Carbon $dateFrom = null,
        private readonly ?Carbon $dateTo = null,
        private readonly string $timezone = 'Africa/Cairo',
        private readonly string $currency = 'EGP',
    ) {
        if ($tenantSlug === '') {
            throw new InvalidArgumentException('Tenant slug is required.');
        }
    }

    public static function fromRequest(): self
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Authentication required for business tool execution.');
        }
        $tenantContext = app(\App\Services\Tenant\TenantContext::class);
        $slug = $tenantContext->slug();
        if ($slug === null || $slug === '') {
            throw new InvalidArgumentException('Tenant context not available.');
        }
        $tz = config('app.timezone', 'Africa/Cairo');
        $from = request()->input('from') ? Carbon::parse(request()->input('from'), $tz)->startOfDay() : now($tz)->startOfMonth();
        $to = request()->input('to') ? Carbon::parse(request()->input('to'), $tz)->endOfDay() : now($tz)->endOfDay();
        return new self(
            tenantSlug: $slug, user: $user,
            branchId: request()->input('branch_id') ? (int) request()->input('branch_id') : null,
            dateFrom: $from, dateTo: $to, timezone: $tz,
        );
    }

    public static function forUser(User $user, string $tenantSlug, ?int $branchId = null): self
    {
        return new self($tenantSlug, $user, $branchId);
    }

    public function tenantSlug(): string { return $this->tenantSlug; }
    public function user(): User { return $this->user; }
    public function userId(): int { return $this->user->id; }
    public function timezone(): string { return $this->timezone; }
    public function currency(): string { return $this->currency; }

    public function permissions(): array
    {
        if ($this->cachedPermissions !== null) return $this->cachedPermissions;
        $keys = [];
        foreach ($this->user->roles as $role) {
            foreach ($role->permissions as $permission) { $keys[] = $permission->key; }
        }
        $this->cachedPermissions = array_values(array_unique($keys));
        return $this->cachedPermissions;
    }

    public function hasPermission(string $key): bool { return in_array($key, $this->permissions(), true); }
    public function hasAllPermissions(array $keys): bool { return empty(array_diff($keys, $this->permissions())); }

    public function branchId(): ?int { return $this->branchId; }

    public function authorizedBranchIds(): ?array
    {
        if ($this->cachedBranchIds !== null) return $this->cachedBranchIds;
        $isOwner = $this->user->roles->contains(fn ($r) => $r->slug === 'owner');
        if ($isOwner) { $this->cachedBranchIds = null; return null; }
        if ($this->branchId !== null) { $this->cachedBranchIds = [$this->branchId]; return $this->cachedBranchIds; }
        $userBranchId = $this->user->branch_id;
        $this->cachedBranchIds = $userBranchId !== null ? [(int) $userBranchId] : [];
        return $this->cachedBranchIds;
    }

    public function hasAllBranchAccess(): bool { return $this->authorizedBranchIds() === null; }

    public function applyBranchScope(\Illuminate\Database\Eloquent\Builder $query, string $column = 'branch_id'): void
    {
        $branchIds = $this->authorizedBranchIds();
        if ($branchIds !== null) { $query->whereIn($column, $branchIds); }
    }

    public function dateFrom(): Carbon { return $this->dateFrom ?? now($this->timezone)->startOfMonth(); }
    public function dateTo(): Carbon { return $this->dateTo ?? now($this->timezone)->endOfDay(); }

    public function applyDateRange(\Illuminate\Database\Eloquent\Builder $query, string $column = 'created_at'): void
    {
        $query->whereBetween($column, [$this->dateFrom()->toDateTimeString(), $this->dateTo()->toDateTimeString()]);
    }

    public function periodLabel(): string
    {
        $from = $this->dateFrom(); $to = $this->dateTo();
        if ($from->isSameDay($to)) return $from->translatedFormat('j F Y');
        if ($from->isSameMonth($to) && $from->isSameYear($to)) return $from->translatedFormat('j') . ' - ' . $to->translatedFormat('j F Y');
        return $from->translatedFormat('j F Y') . ' - ' . $to->translatedFormat('j F Y');
    }

    public function withDateRange(Carbon $from, Carbon $to): self
    {
        return new self(tenantSlug: $this->tenantSlug, user: $this->user, branchId: $this->branchId, dateFrom: $from, dateTo: $to, timezone: $this->timezone, currency: $this->currency);
    }
}
