# Tenant Permissions Map

Source of truth:
- `database/seeders/Tenant/TenantRolePermissionSeeder.php`
- Route middleware usage:
  - `routes/api/tenant.php` (`tenant.permission:<key>`)

## Validation summary

- Route middleware permission keys found in tenant routes: **71**
- Missing route permission keys in seeder: **0**
- Seeder also contains additional foundation keys not yet wired to tenant routes (e.g. `users.manage`, `roles.manage`, `settings.manage`, `payments.create`, `inventory.manage`).

## Permission map

| Permission key | Module | Actions | Frontend screens needing it |
|---|---|---|---|
| `dashboard.view` | Dashboard | view | Tenant dashboard (deferred API) |
| `users.manage` | User Management | manage | Users admin (deferred tenant API routes) |
| `roles.manage` | Role Management | manage | Roles/permissions admin (deferred tenant API routes) |
| `settings.manage` | Settings | manage | Settings admin (deferred tenant settings API) |
| `customers.view` | Customers | list/show | Customers list/details |
| `customers.create` | Customers | create | Customer create form |
| `customers.update` | Customers | update | Customer edit form |
| `customers.delete` | Customers | delete | Customer delete action |
| `customers.export` | Customers | export | Customers export button |
| `branches.view` | Branches | list/show | Branches list/details |
| `branches.create` | Branches | create | Branch create form |
| `branches.update` | Branches | update | Branch edit form |
| `branches.delete` | Branches | delete | Branch delete action |
| `branches.export` | Branches | export | Branches export button |
| `suppliers.view` | Suppliers | list/show | Suppliers list/details |
| `suppliers.create` | Suppliers | create | Supplier create form |
| `suppliers.update` | Suppliers | update | Supplier edit form |
| `suppliers.delete` | Suppliers | delete | Supplier delete action |
| `suppliers.export` | Suppliers | export | Suppliers export button |
| `purchase_orders.view` | Purchase Orders | list/show | Purchase orders list/details |
| `purchase_orders.create` | Purchase Orders | create | Purchase order create form |
| `purchase_orders.update` | Purchase Orders | update | Purchase order edit form |
| `purchase_orders.delete` | Purchase Orders | delete | Purchase order delete action |
| `purchase_orders.export` | Purchase Orders | export | Purchase orders export button |
| `purchase_orders.return` | Purchase Orders | return | Purchase order return action |
| `supplier_payments.view` | Supplier Payments | list | Supplier/PO payment tabs |
| `supplier_payments.create` | Supplier Payments | create | Add supplier payment action |
| `expense_categories.view` | Expense Categories | list/show | Expense category list/details |
| `expense_categories.create` | Expense Categories | create | Expense category create form |
| `expense_categories.update` | Expense Categories | update | Expense category edit form |
| `expense_categories.delete` | Expense Categories | delete | Expense category delete action |
| `expenses.view` | Expenses | list/show | Expenses list/details |
| `expenses.create` | Expenses | create | Expense create form |
| `expenses.update` | Expenses | update | Expense edit form |
| `expenses.delete` | Expenses | delete | Expense delete action |
| `expenses.approve` | Expenses | approve | Expense approval action |
| `expenses.cancel` | Expenses | cancel | Expense cancel action |
| `expenses.pay` | Expenses | pay | Expense pay action |
| `expenses.summary` | Expenses | summary view | Expense summary widget/page |
| `expenses.export` | Expenses | export | Expenses export button |
| `cash_movements.view` | Cash Movements | list | Cash movement list |
| `cash_movements.create` | Cash Movements | create | Manual cash movement form |
| `cashboxes.view` | Cashboxes | list/show/transactions/summary | Cashboxes list/detail/transactions |
| `cashboxes.create` | Cashboxes | create | Cashbox create form |
| `cashboxes.update` | Cashboxes | update | Cashbox edit form |
| `cashboxes.delete` | Cashboxes | delete | Cashbox delete action |
| `cashboxes.recalculate` | Cashboxes | recalculate | Cashbox recalculate action |
| `cashboxes.export` | Cashboxes | export | Cashboxes export button |
| `payments.view` | Standalone Payments | list/show | Payments list/details |
| `payments.create` | Standalone Payments | create (reserved) | Future payment create screen if separated |
| `payments.pay` | Standalone Payments | mark paid | Payments pay action |
| `payments.cancel` | Standalone Payments | cancel | Payments cancel action |
| `payments.export` | Standalone Payments | export | Payments export button |
| `dress_categories.view` | Dress Categories | list/show | Categories & subcategories list |
| `dress_categories.create` | Dress Categories | create | Category/subcategory create form |
| `dress_categories.update` | Dress Categories | update | Category/subcategory edit form |
| `dress_categories.delete` | Dress Categories | delete | Category/subcategory delete action |
| `dresses.view` | Dresses | list/show + availability/history endpoints | Dresses list/details/availability views |
| `dresses.create` | Dresses | create | Dress create form |
| `dresses.update` | Dresses | update | Dress edit form |
| `dresses.delete` | Dresses | delete | Dress delete action |
| `dresses.export` | Dresses | export | Dresses export button |
| `inventory.view` | Inventory Movements | list | Dress inventory movement history |
| `inventory.manage` | Inventory | manage (reserved) | Future inventory management actions |
| `invoices.view` | Invoices | list/show | Invoice list/details |
| `invoices.create` | Invoices | create | Invoice create form |
| `invoices.update` | Invoices | update | Invoice edit form |
| `invoices.delete` | Invoices | delete | Invoice delete action |
| `invoices.cancel` | Invoices | cancel | Invoice cancel action |
| `invoices.export` | Invoices | export | Invoices export button |
| `invoice_payments.view` | Invoice Payments | list | Invoice payments tab |
| `invoice_payments.create` | Invoice Payments | create | Add invoice payment action |
| `invoice_delivery.view` | Delivery/Return | delivery records list | Delivery records tab |
| `invoice_delivery.deliver` | Delivery/Return | deliver | Deliver invoice action |
| `invoice_delivery.return` | Delivery/Return | return | Return invoice action |
| `security_deposit.view` | Security Deposits | transactions list | Security deposit history tab |
| `security_deposit.deduct` | Security Deposits | deduction create | Security deposit deduction action |
