<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

class TenantRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = Role::query()->updateOrCreate(
            ['slug' => 'owner'],
            ['name' => 'Owner']
        );

        $permissions = [
            ['name' => 'Dashboard View', 'key' => 'dashboard.view'],
            ['name' => 'Users Manage', 'key' => 'users.manage'],
            ['name' => 'Roles Manage', 'key' => 'roles.manage'],
            ['name' => 'Settings Manage', 'key' => 'settings.manage'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permissionData) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $permissionData['key']],
                $permissionData
            );
            $permissionIds[] = $permission->id;
        }

        $ownerRole->permissions()->sync($permissionIds);
    }
}
