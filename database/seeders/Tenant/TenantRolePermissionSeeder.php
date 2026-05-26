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
            ['name' => 'Customers View', 'key' => 'customers.view'],
            ['name' => 'Customers Create', 'key' => 'customers.create'],
            ['name' => 'Customers Update', 'key' => 'customers.update'],
            ['name' => 'Customers Delete', 'key' => 'customers.delete'],
            ['name' => 'Suppliers View', 'key' => 'suppliers.view'],
            ['name' => 'Suppliers Create', 'key' => 'suppliers.create'],
            ['name' => 'Suppliers Update', 'key' => 'suppliers.update'],
            ['name' => 'Suppliers Delete', 'key' => 'suppliers.delete'],
            ['name' => 'Purchase Orders View', 'key' => 'purchase_orders.view'],
            ['name' => 'Purchase Orders Create', 'key' => 'purchase_orders.create'],
            ['name' => 'Purchase Orders Update', 'key' => 'purchase_orders.update'],
            ['name' => 'Purchase Orders Delete', 'key' => 'purchase_orders.delete'],
            ['name' => 'Supplier Payments View', 'key' => 'supplier_payments.view'],
            ['name' => 'Supplier Payments Create', 'key' => 'supplier_payments.create'],
            ['name' => 'Expense Categories View', 'key' => 'expense_categories.view'],
            ['name' => 'Expense Categories Create', 'key' => 'expense_categories.create'],
            ['name' => 'Expense Categories Update', 'key' => 'expense_categories.update'],
            ['name' => 'Expense Categories Delete', 'key' => 'expense_categories.delete'],
            ['name' => 'Expenses View', 'key' => 'expenses.view'],
            ['name' => 'Expenses Create', 'key' => 'expenses.create'],
            ['name' => 'Expenses Update', 'key' => 'expenses.update'],
            ['name' => 'Expenses Delete', 'key' => 'expenses.delete'],
            ['name' => 'Cash Movements View', 'key' => 'cash_movements.view'],
            ['name' => 'Cash Movements Create', 'key' => 'cash_movements.create'],
            ['name' => 'Dress Categories View', 'key' => 'dress_categories.view'],
            ['name' => 'Dress Categories Create', 'key' => 'dress_categories.create'],
            ['name' => 'Dress Categories Update', 'key' => 'dress_categories.update'],
            ['name' => 'Dress Categories Delete', 'key' => 'dress_categories.delete'],
            ['name' => 'Dresses View', 'key' => 'dresses.view'],
            ['name' => 'Dresses Create', 'key' => 'dresses.create'],
            ['name' => 'Dresses Update', 'key' => 'dresses.update'],
            ['name' => 'Dresses Delete', 'key' => 'dresses.delete'],
            ['name' => 'Inventory View', 'key' => 'inventory.view'],
            ['name' => 'Inventory Manage', 'key' => 'inventory.manage'],
            ['name' => 'Invoices View', 'key' => 'invoices.view'],
            ['name' => 'Invoices Create', 'key' => 'invoices.create'],
            ['name' => 'Invoices Update', 'key' => 'invoices.update'],
            ['name' => 'Invoices Delete', 'key' => 'invoices.delete'],
            ['name' => 'Invoice Payments View', 'key' => 'invoice_payments.view'],
            ['name' => 'Invoice Payments Create', 'key' => 'invoice_payments.create'],
            ['name' => 'Invoice Delivery View', 'key' => 'invoice_delivery.view'],
            ['name' => 'Invoice Deliver', 'key' => 'invoice_delivery.deliver'],
            ['name' => 'Invoice Return', 'key' => 'invoice_delivery.return'],
            ['name' => 'Security Deposit View', 'key' => 'security_deposit.view'],
            ['name' => 'Security Deposit Deduct', 'key' => 'security_deposit.deduct'],
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
