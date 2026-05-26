<?php

namespace App\Services\Auth;

use App\Models\Tenant\User;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function login(string $email, string $password): array
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'workspace' => ['Tenant workspace is required.'],
            ]);
        }

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
            'token' => $user->createToken('tenant-token')->plainTextToken,
            'user' => $user,
            'tenant' => $tenant,
            'permissions' => $permissions,
            'plan' => $tenant->plan,
        ];
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
