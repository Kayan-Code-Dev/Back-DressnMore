<?php

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $receivePermission = Permission::query()->firstOrCreate(
            ['key' => 'purchase_orders.receive'],
            ['name' => 'Purchase Orders Receive', 'group' => 'purchase_orders']
        );

        // Add to all roles that have purchase_orders.view
        $viewPermission = Permission::query()->where('key', 'purchase_orders.view')->first();
        if ($viewPermission !== null) {
            $rolesWithView = Role::query()->whereHas('permissions', function ($q) use ($viewPermission) {
                $q->where('permission_id', $viewPermission->id);
            })->get();

            foreach ($rolesWithView as $role) {
                $role->permissions()->syncWithoutDetaching([$receivePermission->id]);
            }
        }

        // Also ensure return permission exists
        $returnPermission = Permission::query()->firstOrCreate(
            ['key' => 'purchase_orders.return'],
            ['name' => 'Purchase Orders Return', 'group' => 'purchase_orders']
        );

        if ($viewPermission !== null) {
            foreach ($rolesWithView as $role) {
                $role->permissions()->syncWithoutDetaching([$returnPermission->id]);
            }
        }
    }

    public function down(): void
    {
        Permission::query()->where('key', 'purchase_orders.receive')->delete();
    }
};
