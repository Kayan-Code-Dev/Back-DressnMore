<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrEmployeeAccountService
{
    public function __construct(
        private readonly TenantUserDirectoryService $tenantUserDirectoryService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $account
     */
    public function createForEmployee(HrEmployee $employee, array $account): User
    {
        $email = strtolower(trim((string) ($account['email'] ?? $employee->email ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'user_account.email' => ['حساب الدخول يتطلب بريداً إلكترونياً.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'user_account.email' => ['البريد مستخدم لحساب آخر.'],
            ]);
        }

        $user = User::query()->create([
            'name' => $employee->full_name,
            'email' => $email,
            'password' => (string) $account['password'],
            'phone' => $employee->phone,
            'status' => $this->userStatusForEmployee($employee),
        ]);

        $this->syncUserAccess($user, $account);

        $employee->user_id = $user->id;
        $employee->save();

        $this->registerInDirectory($email);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $account
     */
    public function updateForEmployee(HrEmployee $employee, array $account): ?User
    {
        $user = $employee->user;

        if (! $user instanceof User) {
            if ($account === []) {
                return null;
            }

            return $this->createForEmployee($employee, $account);
        }

        $email = array_key_exists('email', $account)
            ? strtolower(trim((string) $account['email']))
            : strtolower((string) $user->email);

        if ($email !== '' && $email !== strtolower((string) $user->email)) {
            $exists = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'user_account.email' => ['البريد مستخدم لحساب آخر.'],
                ]);
            }
            $user->email = $email;
        }

        if (! empty($account['password'])) {
            $user->password = (string) $account['password'];
        }

        if (array_key_exists('name', $account) || $employee->wasChanged('full_name')) {
            $user->name = $employee->full_name;
        }

        if ($employee->wasChanged('phone')) {
            $user->phone = $employee->phone;
        }

        $user->status = $this->userStatusForEmployee($employee);
        $user->save();

        if (array_key_exists('role_id', $account) || array_key_exists('permission_ids', $account)) {
            $this->syncUserAccess($user, $account);
        }

        $this->registerInDirectory(strtolower((string) $user->email));

        return $user;
    }

    private function userStatusForEmployee(HrEmployee $employee): string
    {
        return ($employee->status ?? 'active') === 'active' ? 'active' : 'inactive';
    }

    private function registerInDirectory(string $email): void
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return;
        }

        $this->tenantUserDirectoryService->register(
            $this->tenantContext->requireTenant(),
            $normalized,
        );
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function syncUserAccess(User $user, array $account): void
    {
        $roleId = isset($account['role_id']) ? (int) $account['role_id'] : null;
        $permissionIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) ($account['permission_ids'] ?? [])
        )));

        if ($roleId) {
            $role = Role::query()->find($roleId);
            if (! $role) {
                throw ValidationException::withMessages([
                    'user_account.role_id' => ['الدور المحدد غير موجود.'],
                ]);
            }
            if ($role->slug === 'owner') {
                throw ValidationException::withMessages([
                    'user_account.role_id' => ['لا يمكن تعيين دور المالك من شاشة الموارد البشرية.'],
                ]);
            }
            $user->roles()->sync([$role->id]);

            return;
        }

        if ($permissionIds === []) {
            throw ValidationException::withMessages([
                'user_account.permission_ids' => ['اختر دوراً أو صلاحية واحدة على الأقل.'],
            ]);
        }

        $validCount = Permission::query()->whereIn('id', $permissionIds)->count();
        if ($validCount !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'user_account.permission_ids' => ['بعض الصلاحيات غير صالحة.'],
            ]);
        }

        $slug = 'hr-staff-'.$user->id;
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => 'موظف — '.$user->name]
        );
        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([$role->id]);
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string,permission_ids:list<int>}>
     */
    public function listRoles(): array
    {
        return Role::query()
            ->with('permissions:id')
            ->where('slug', '!=', 'owner')
            ->orderBy('name')
            ->get()
            ->map(static fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'permission_ids' => $role->permissions->pluck('id')->all(),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,key:string,name:string,group:string}>
     */
    public function listPermissions(): array
    {
        return Permission::query()
            ->orderBy('key')
            ->get()
            ->map(static function (Permission $permission): array {
                $key = (string) $permission->key;
                $group = Str::before($key, '.');

                return [
                    'id' => $permission->id,
                    'key' => $key,
                    'name' => $permission->name,
                    'group' => $group !== '' ? $group : 'general',
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function presentAccount(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->loadMissing('roles.permissions');

        $role = $user->roles->first();
        $permissionIds = $user->roles
            ->flatMap(static fn (Role $r) => $r->permissions)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $user->status,
            'role_id' => $role?->id,
            'role_name' => $role?->name,
            'role_slug' => $role?->slug,
            'permission_ids' => $permissionIds,
        ];
    }
}
