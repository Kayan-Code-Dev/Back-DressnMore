<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantUserDirectoryService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantUserDirectoryService $tenantUserDirectoryService,
        private readonly TenantDatabaseManager $tenantDatabaseManager,
    ) {}

    public function login(string $email, string $password): array
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'workspace' => ['Tenant workspace is required.'],
            ]);
        }

        if (! $this->tenantUserDirectoryService->emailBelongsToTenant($tenant, $email)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $this->tenantDatabaseManager->connect($tenant);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ((string) $user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $permissions = $this->permissionsForUser($user);

        return [
            'token' => $this->issueTenantToken($user, $tenant),
            'user' => $user,
            'tenant' => $tenant->loadMissing('plan'),
            'permissions' => $permissions,
            'plan' => $tenant->plan,
        ];
    }

    public function issueTenantToken(User $user, Tenant $tenant): string
    {
        $tokenResult = $user->createToken('tenant-token');
        $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

        return $tokenResult->plainTextToken;
    }

    /**
     * @return list<string>
     */
    public function permissionsForUser(User $user): array
    {
        return $user->roles()
            ->with('permissions:id,key')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('key')
            ->unique()
            ->values()
            ->all();
    }
}
