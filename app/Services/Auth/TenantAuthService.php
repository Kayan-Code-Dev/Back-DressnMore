<?php

namespace App\Services\Auth;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantUserDirectoryService;
use Carbon\CarbonImmutable;
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
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $tenant = $this->tenantUserDirectoryService->findTenantByEmail($normalizedEmail);

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $this->assertTenantCanAuthenticate($tenant);

        $this->tenantContext->setTenant($tenant);
        $this->tenantDatabaseManager->connect($tenant);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
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

    private function assertTenantCanAuthenticate(Tenant $tenant): void
    {
        $status = (string) $tenant->status;
        if ($status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This tenant account is not available.'],
            ]);
        }

        if ($tenant->subscription_ends_at !== null) {
            $endsAt = CarbonImmutable::parse((string) $tenant->subscription_ends_at);
            if ($endsAt->lt(CarbonImmutable::today())) {
                throw ValidationException::withMessages([
                    'email' => ['Tenant subscription has expired.'],
                ]);
            }
        }
    }
}
