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
            ['name' => 'Customers Export', 'key' => 'customers.export'],
            ['name' => 'Branches View', 'key' => 'branches.view'],
            ['name' => 'Branches Create', 'key' => 'branches.create'],
            ['name' => 'Branches Update', 'key' => 'branches.update'],
            ['name' => 'Branches Delete', 'key' => 'branches.delete'],
            ['name' => 'Branches Export', 'key' => 'branches.export'],
            ['name' => 'Suppliers View', 'key' => 'suppliers.view'],
            ['name' => 'Suppliers Create', 'key' => 'suppliers.create'],
            ['name' => 'Suppliers Update', 'key' => 'suppliers.update'],
            ['name' => 'Suppliers Delete', 'key' => 'suppliers.delete'],
            ['name' => 'Suppliers Export', 'key' => 'suppliers.export'],
            ['name' => 'Purchase Orders View', 'key' => 'purchase_orders.view'],
            ['name' => 'Purchase Orders Create', 'key' => 'purchase_orders.create'],
            ['name' => 'Purchase Orders Update', 'key' => 'purchase_orders.update'],
            ['name' => 'Purchase Orders Delete', 'key' => 'purchase_orders.delete'],
            ['name' => 'Purchase Orders Export', 'key' => 'purchase_orders.export'],
            ['name' => 'Purchase Orders Return', 'key' => 'purchase_orders.return'],
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
            ['name' => 'Expenses Approve', 'key' => 'expenses.approve'],
            ['name' => 'Expenses Cancel', 'key' => 'expenses.cancel'],
            ['name' => 'Expenses Pay', 'key' => 'expenses.pay'],
            ['name' => 'Expenses Summary', 'key' => 'expenses.summary'],
            ['name' => 'Expenses Export', 'key' => 'expenses.export'],
            ['name' => 'Cash Movements View', 'key' => 'cash_movements.view'],
            ['name' => 'Cash Movements Create', 'key' => 'cash_movements.create'],
            ['name' => 'Cashboxes View', 'key' => 'cashboxes.view'],
            ['name' => 'Cashboxes Create', 'key' => 'cashboxes.create'],
            ['name' => 'Cashboxes Update', 'key' => 'cashboxes.update'],
            ['name' => 'Cashboxes Delete', 'key' => 'cashboxes.delete'],
            ['name' => 'Cashboxes Recalculate', 'key' => 'cashboxes.recalculate'],
            ['name' => 'Cashboxes Export', 'key' => 'cashboxes.export'],
            ['name' => 'Payments View', 'key' => 'payments.view'],
            ['name' => 'Payments Create', 'key' => 'payments.create'],
            ['name' => 'Payments Pay', 'key' => 'payments.pay'],
            ['name' => 'Payments Cancel', 'key' => 'payments.cancel'],
            ['name' => 'Payments Export', 'key' => 'payments.export'],
            ['name' => 'Dress Categories View', 'key' => 'dress_categories.view'],
            ['name' => 'Dress Categories Create', 'key' => 'dress_categories.create'],
            ['name' => 'Dress Categories Update', 'key' => 'dress_categories.update'],
            ['name' => 'Dress Categories Delete', 'key' => 'dress_categories.delete'],
            ['name' => 'Dresses View', 'key' => 'dresses.view'],
            ['name' => 'Dresses Create', 'key' => 'dresses.create'],
            ['name' => 'Dresses Update', 'key' => 'dresses.update'],
            ['name' => 'Dresses Delete', 'key' => 'dresses.delete'],
            ['name' => 'Dresses Export', 'key' => 'dresses.export'],
            ['name' => 'Inventory View', 'key' => 'inventory.view'],
            ['name' => 'Inventory Manage', 'key' => 'inventory.manage'],
            ['name' => 'Products View', 'key' => 'products.view'],
            ['name' => 'Products Create', 'key' => 'products.create'],
            ['name' => 'Products Update', 'key' => 'products.update'],
            ['name' => 'Products Delete', 'key' => 'products.delete'],
            ['name' => 'Product Transfers View', 'key' => 'product_transfers.view'],
            ['name' => 'Product Transfers Create', 'key' => 'product_transfers.create'],
            ['name' => 'Product Transfers Confirm', 'key' => 'product_transfers.confirm'],
            ['name' => 'Product Transfers Reject', 'key' => 'product_transfers.reject'],
            ['name' => 'Product Transfers Delete', 'key' => 'product_transfers.delete'],
            ['name' => 'Invoices View', 'key' => 'invoices.view'],
            ['name' => 'Invoices Create', 'key' => 'invoices.create'],
            ['name' => 'Invoices Update', 'key' => 'invoices.update'],
            ['name' => 'Invoices Delete', 'key' => 'invoices.delete'],
            ['name' => 'Invoices Cancel', 'key' => 'invoices.cancel'],
            ['name' => 'Invoices Export', 'key' => 'invoices.export'],
            ['name' => 'Tailoring View', 'key' => 'tailoring.view'],
            ['name' => 'Tailoring Create', 'key' => 'tailoring.create'],
            ['name' => 'Tailoring Update', 'key' => 'tailoring.update'],
            ['name' => 'Tailoring Change Stage', 'key' => 'tailoring.change_stage'],
            ['name' => 'Tailoring Override Stage', 'key' => 'tailoring.override_stage'],
            ['name' => 'Tailoring View Workshop', 'key' => 'tailoring.view_workshop'],
            ['name' => 'Tailoring View Schedule', 'key' => 'tailoring.view_schedule'],
            ['name' => 'Invoice Payments View', 'key' => 'invoice_payments.view'],
            ['name' => 'Invoice Payments Create', 'key' => 'invoice_payments.create'],
            ['name' => 'Invoice Delivery View', 'key' => 'invoice_delivery.view'],
            ['name' => 'Invoice Deliver', 'key' => 'invoice_delivery.deliver'],
            ['name' => 'Invoice Return', 'key' => 'invoice_delivery.return'],
            ['name' => 'Security Deposit View', 'key' => 'security_deposit.view'],
            ['name' => 'Security Deposit Deduct', 'key' => 'security_deposit.deduct'],
            ['name' => 'Reports View', 'key' => 'reports.view'],
            ['name' => 'Reports Sales', 'key' => 'reports.sales'],
            ['name' => 'Reports Tailoring', 'key' => 'reports.tailoring'],
            ['name' => 'Reports Rental', 'key' => 'reports.rental'],
            ['name' => 'Reports Deliveries', 'key' => 'reports.deliveries'],
            ['name' => 'Reports Returns', 'key' => 'reports.returns'],
            ['name' => 'Reports Customers', 'key' => 'reports.customers'],
            ['name' => 'Reports Inventory', 'key' => 'reports.inventory'],
            ['name' => 'Reports Expenses', 'key' => 'reports.expenses'],
            ['name' => 'Reports Cash', 'key' => 'reports.cash'],
            ['name' => 'Reports Accounting', 'key' => 'reports.accounting'],
            ['name' => 'Reports Payments', 'key' => 'reports.payments'],
            ['name' => 'Reports Suppliers', 'key' => 'reports.suppliers'],
            ['name' => 'Accounting View', 'key' => 'accounting.view'],
            ['name' => 'Journal Entries View', 'key' => 'accounting.journal_entries.view'],
            ['name' => 'Journal Entries Create', 'key' => 'accounting.journal_entries.create'],
            ['name' => 'Journal Entries Update', 'key' => 'accounting.journal_entries.update'],
            ['name' => 'Journal Entries Approve', 'key' => 'accounting.journal_entries.approve'],
            ['name' => 'Journal Entries Cancel', 'key' => 'accounting.journal_entries.cancel'],
            ['name' => 'Journal Entries Reverse', 'key' => 'accounting.journal_entries.reverse'],
            ['name' => 'Journal Entries Export', 'key' => 'accounting.journal_entries.export'],
            ['name' => 'Settings View', 'key' => 'settings.view'],
            ['name' => 'Settings Profile', 'key' => 'settings.profile'],
            ['name' => 'HR View', 'key' => 'hr.view'],
            ['name' => 'HR Dashboard View', 'key' => 'hr.dashboard.view'],
            ['name' => 'HR Employees View', 'key' => 'hr.employees.view'],
            ['name' => 'HR Employees Create', 'key' => 'hr.employees.create'],
            ['name' => 'HR Employees Update', 'key' => 'hr.employees.update'],
            ['name' => 'HR Employees Delete', 'key' => 'hr.employees.delete'],
            ['name' => 'HR Employees Status', 'key' => 'hr.employees.status'],
            ['name' => 'HR Documents View', 'key' => 'hr.documents.view'],
            ['name' => 'HR Documents Upload', 'key' => 'hr.documents.upload'],
            ['name' => 'HR Documents Delete', 'key' => 'hr.documents.delete'],
            ['name' => 'HR Settings View', 'key' => 'hr.settings.view'],
            ['name' => 'HR Settings Update', 'key' => 'hr.settings.update'],
            ['name' => 'HR Departments View', 'key' => 'hr.departments.view'],
            ['name' => 'HR Departments Create', 'key' => 'hr.departments.create'],
            ['name' => 'HR Departments Update', 'key' => 'hr.departments.update'],
            ['name' => 'HR Departments Delete', 'key' => 'hr.departments.delete'],
            ['name' => 'HR Job Titles View', 'key' => 'hr.job_titles.view'],
            ['name' => 'HR Job Titles Create', 'key' => 'hr.job_titles.create'],
            ['name' => 'HR Job Titles Update', 'key' => 'hr.job_titles.update'],
            ['name' => 'HR Job Titles Delete', 'key' => 'hr.job_titles.delete'],
            ['name' => 'HR Shifts View', 'key' => 'hr.shifts.view'],
            ['name' => 'HR Shifts Create', 'key' => 'hr.shifts.create'],
            ['name' => 'HR Shifts Update', 'key' => 'hr.shifts.update'],
            ['name' => 'HR Shifts Delete', 'key' => 'hr.shifts.delete'],
            ['name' => 'HR Attendance View', 'key' => 'hr.attendance.view'],
            ['name' => 'HR Attendance Create', 'key' => 'hr.attendance.create'],
            ['name' => 'HR Attendance Update', 'key' => 'hr.attendance.update'],
            ['name' => 'HR Leaves View', 'key' => 'hr.leaves.view'],
            ['name' => 'HR Leaves Create', 'key' => 'hr.leaves.create'],
            ['name' => 'HR Leaves Status', 'key' => 'hr.leaves.status'],
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

        $journalPermissionIds = Permission::query()
            ->whereIn('key', [
                'accounting.journal_entries.view',
                'accounting.journal_entries.create',
                'accounting.journal_entries.update',
                'accounting.journal_entries.approve',
                'accounting.journal_entries.cancel',
                'accounting.journal_entries.reverse',
                'accounting.journal_entries.export',
            ])
            ->pluck('id')
            ->all();

        Role::query()
            ->where('slug', '!=', 'owner')
            ->whereHas('permissions', fn ($query) => $query->where('key', 'accounting.view'))
            ->each(function (Role $role) use ($journalPermissionIds): void {
                $role->permissions()->syncWithoutDetaching($journalPermissionIds);
            });

        $managerPermissionIds = Permission::query()
            ->whereIn('key', [
                'tailoring.view',
                'tailoring.update',
                'tailoring.change_stage',
                'tailoring.view_workshop',
                'tailoring.view_schedule',
                'invoices.view',
                'invoices.create',
                'customers.view',
                'customers.create',
            ])
            ->pluck('id')
            ->all();

        $managerRole = Role::query()->updateOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager']
        );
        $managerRole->permissions()->syncWithoutDetaching($managerPermissionIds);

        $this->call(AccountSeeder::class);
    }
}
