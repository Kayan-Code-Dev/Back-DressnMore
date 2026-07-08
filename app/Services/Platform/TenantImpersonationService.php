<?php

namespace App\Services\Platform;

use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\TenantProvisioningLog;
use App\Models\Tenant\User;
use App\Services\Auth\TenantAuthService;
use App\Services\Tenant\TenantDatabaseManager;
use App\Support\TenantSubscriptionPresenter;
use RuntimeException;

class TenantImpersonationService
{
    public function __construct(
        private readonly TenantDatabaseManager $tenantDatabaseManager,
        private readonly TenantAuthService $tenantAuthService,
        private readonly TenantSubscriptionPresenter $tenantSubscriptionPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function impersonate(Tenant $tenant, SuperAdmin $admin, ?int $userId = null): array
    {
        if ((string) $tenant->status !== 'active') {
            throw new RuntimeException('لا يمكن الدخول إلى tenant غير نشط');
        }

        $tenant->loadMissing('plan');
        $this->tenantDatabaseManager->connect($tenant);

        $user = $this->resolveUser($tenant, $userId);

        if ((string) $user->status !== 'active') {
            throw new RuntimeException('حساب المستخدم غير نشط');
        }

        $token = $this->tenantAuthService->issueTenantToken($user, $tenant);
        $permissions = $this->tenantAuthService->permissionsForUser($user);

        TenantProvisioningLog::query()->create([
            'tenant_id' => $tenant->id,
            'step' => 'admin_impersonation',
            'status' => 'success',
            'message' => 'Platform admin impersonation',
            'context' => [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'user_id' => $user->id,
                'user_email' => $user->email,
            ],
        ]);

        return [
            'impersonation' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'permissions' => $permissions,
            'subscription' => $this->tenantSubscriptionPresenter->forTenant($tenant),
        ];
    }

    private function resolveUser(Tenant $tenant, ?int $userId): User
    {
        if ($userId !== null) {
            $user = User::query()->find($userId);
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException('المستخدم المطلوب غير موجود في هذا الـ tenant');
        }

        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $adminEmail = strtolower(trim((string) ($metadata['admin_email'] ?? '')));

        if ($adminEmail !== '') {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$adminEmail])
                ->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        $owner = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('slug', 'owner'))
            ->orderBy('id')
            ->first();

        if ($owner instanceof User) {
            return $owner;
        }

        $fallback = User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($fallback instanceof User) {
            return $fallback;
        }

        throw new RuntimeException('لا يوجد مستخدم نشط للدخول إليه في هذا الـ tenant');
    }
}
