# API Contract

This document freezes the current API contract for frontend integration.

## Global API Rules

- Base URL: `/api`
- All API responses are JSON only.
- Tenant APIs require `X-Tenant` and `Authorization: Bearer <token>` unless endpoint is tenant login.
- Success envelope:

```json
{
  "success": true,
  "message": "Success",
  "data": {},
  "meta": {}
}
```

- Error envelope:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

- Paginated envelope:

```json
{
  "success": true,
  "message": "Success",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

## Required Headers

- `Accept: application/json`
- `Content-Type: application/json` (for POST/PUT)
- `Authorization: Bearer <token>` (protected routes)
- `X-Tenant: <workspace-slug>` (tenant routes except unauthenticated missing-tenant test case)

---

## Platform Auth

### POST `/api/platform/login`
- Permission: Public
- Body:
  - `email` (required, email)
  - `password` (required, string)
- Response data:
  - `token`
  - `user`
- Validation errors: 422
- Frontend notes: persist token and use on platform protected routes.

### POST `/api/platform/logout`
- Permission: authenticated platform user
- Response data: `null`

### GET `/api/platform/me`
- Permission: authenticated platform user
- Response data:
  - `user`

### GET `/api/platform/health`
- Permission: Public
- Response data:
  - `app_name`
  - `environment`
  - `central_database_connection`
  - `timestamp`

---

## Tenant Auth

### POST `/api/tenant/login`
- Permission: Public
- Middleware: `identify.tenant`, `check.tenant.subscription`, `set.tenant.database`
- Body:
  - `workspace` (required)
  - `email` (required)
  - `password` (required)
- Response data:
  - `token`
  - `user`
  - `tenant`
  - `permissions`
  - `plan`

### POST `/api/tenant/logout`
- Permission: authenticated tenant user

### GET `/api/tenant/me`
- Permission: authenticated tenant user
- Response data:
  - `user`
  - `tenant`
  - `permissions`
  - `plan`

### GET `/api/tenant/health`
- Permission: tenant identified + subscribed
- Response data:
  - `ok`
  - `timestamp`

---

## Tenant Provisioning

Base: `/api/platform/tenants`

Permission:
- authenticated platform admin (`auth:sanctum` + platform admin middleware)

### GET `/api/platform/tenants`
- Query:
  - `search` (name/slug/database_name)
  - `status`
  - `plan_id`
  - `per_page`
- Response: paginated `Tenant[]`

### POST `/api/platform/tenants`
- Body:
  - `name` required
  - `slug` optional
  - `database_name` optional
  - `plan_id` optional
  - `subscription_starts_at` optional
  - `subscription_ends_at` optional
  - `metadata` optional object
- Backend behavior:
  - create central tenant row in `provisioning`
  - create tenant database if missing
  - connect and run tenant migrations
  - run tenant seeders (roles/permissions + settings)
  - set tenant status to `active` or `provisioning_failed`
  - write provisioning logs to `tenant_provisioning_logs`

### POST `/api/platform/tenants/{tenant}/suspend`
- Sets tenant status to `suspended`.

### POST `/api/platform/tenants/{tenant}/activate`
- Sets tenant status to `active`.

### POST `/api/platform/tenants/{tenant}/renew`
- Body (optional):
  - `days` integer (default 30)
  - `subscription_ends_at` explicit date
- Backend behavior:
  - extends (or sets) `subscription_ends_at`
  - enforces `active` status

---

## Customers

Base: `/api/tenant/customers`

Headers: `Authorization`, `X-Tenant`

Permissions:
- index/show: `customers.view`
- store: `customers.create`
- update: `customers.update`
- delete: `customers.delete`

### GET `/`
- Query:
  - `search` (name/phone/whatsapp/email)
  - `per_page`
- Response: paginated `Customer[]`

### POST `/`
- Body:
  - `name` required
  - `phone`, `whatsapp`, `email`, `address`, `national_id`, `notes` optional
  - `status` (`active|inactive`) optional

### GET `/{customer}`
- Response: `Customer`

### PUT `/{customer}`
- Same body shape as create.

### DELETE `/{customer}`
- Soft delete.

---

## Suppliers

Base: `/api/tenant/suppliers`

Permissions:
- index/show: `suppliers.view`
- store: `suppliers.create`
- update: `suppliers.update`
- delete: `suppliers.delete`

### GET `/`
- Query:
  - `search` (name/phone/whatsapp/email)
  - `status` (`active|inactive`)
  - `per_page`
- Response: paginated `Supplier[]`

### POST `/`
- Body:
  - `name` required
  - `phone`, `whatsapp`, `email`, `address`, `tax_number`, `notes` optional
  - `opening_balance` optional decimal
  - `status` optional (`active|inactive`)
- Response includes:
  - `opening_balance`
  - `current_balance`
  - `total_purchase_orders`
  - `total_paid`
  - `total_remaining`

### GET `/{supplier}`
### PUT `/{supplier}`
### DELETE `/{supplier}`
- Soft delete.

---

## Purchase Orders

Base: `/api/tenant/purchase-orders`

Permissions:
- index/show: `purchase_orders.view`
- store: `purchase_orders.create`
- update: `purchase_orders.update`
- delete: `purchase_orders.delete`

### GET `/`
- Query:
  - `search` (purchase_order_number/supplier name)
  - `supplier_id`
  - `status` (`draft|confirmed|partially_paid|paid|cancelled`)
  - `date_from`
  - `date_to`
  - `per_page`
- Response: paginated `PurchaseOrder[]`

### POST `/`
- Body:
  - `supplier_id` required
  - `status` optional
  - `discount`, `tax` optional
  - `order_date`, `notes` optional
  - `items` required array:
    - `item_name` required
    - `description` optional
    - `quantity` optional > 0 (default 1)
    - `unit_price` optional >= 0
- Backend behavior:
  - auto `purchase_order_number` as `PO-YYYYMMDD-####`
  - calculates `subtotal`, `total`, `paid_amount`, `remaining_amount`
  - updates status according to payment state
  - updates supplier `current_balance`

### GET `/{purchaseOrder}`
### PUT `/{purchaseOrder}`
### DELETE `/{purchaseOrder}`
- Delete uses soft delete.

---

## Supplier Payments

Permissions:
- list: `supplier_payments.view`
- create: `supplier_payments.create`

### GET `/api/tenant/suppliers/{supplier}/payments`
- Query: `per_page`
- Response: paginated `SupplierPayment[]`

### POST `/api/tenant/suppliers/{supplier}/payments`
- Body:
  - `purchase_order_id` optional (must belong to supplier if provided)
  - `amount` required > 0
  - `method` optional (`cash|instapay|vodafone_cash|bank_transfer`)
  - `reference`, `paid_at`, `notes` optional
- Backend behavior:
  - creates supplier payment record
  - updates purchase order financials and status if linked
  - updates supplier `current_balance`
  - creates cash movement:
    - `type=supplier_payment`
    - `direction=out`
    - `reference_type=supplier_payment`
    - `reference_id=supplier_payment.id`
    - `movement_date=paid_at or now`

### GET `/api/tenant/purchase-orders/{purchaseOrder}/payments` (optional foundation route)
- Query: `per_page`
- Response: paginated `SupplierPayment[]`

---

## Expense Categories

Base: `/api/tenant/expense-categories`

Permissions:
- index/show: `expense_categories.view`
- store: `expense_categories.create`
- update: `expense_categories.update`
- delete: `expense_categories.delete`

### GET `/`
- Query:
  - `search` (name)
  - `status` (`active|inactive`)
  - `per_page`
- Response: paginated `ExpenseCategory[]`

### POST `/`
- Body:
  - `name` required
  - `slug` optional unique
  - `description` optional
  - `status` optional (`active|inactive`)

### GET `/{expenseCategory}`
### PUT `/{expenseCategory}`
### DELETE `/{expenseCategory}`
- Soft delete.

---

## Expenses

Base: `/api/tenant/expenses`

Permissions:
- index/show: `expenses.view`
- store: `expenses.create`
- update: `expenses.update`
- delete: `expenses.delete`

### GET `/`
- Query:
  - `search` (description/reference/notes)
  - `expense_category_id`
  - `method`
  - `date_from`
  - `date_to`
  - `per_page`
- Response: paginated `Expense[]`

### POST `/`
- Body:
  - `expense_category_id` optional
  - `amount` required > 0
  - `method` optional (same values as payment methods)
  - `reference` optional
  - `expense_date` required date
  - `description`, `notes` optional
- Backend behavior:
  - creates expense record
  - creates cash movement:
    - `type=expense`
    - `direction=out`
    - `reference_type=expense`
    - `reference_id=expense.id`
    - `movement_date=expense_date`

### GET `/{expense}`
### PUT `/{expense}`
- Backend behavior:
  - updates expense record
  - updates linked expense cash movement (same reference pair)

### DELETE `/{expense}`
- Soft delete expense and linked cash movement.

---

## Cash Movements

Base: `/api/tenant/cash-movements`

Permissions:
- index: `cash_movements.view`
- store: `cash_movements.create`

### GET `/`
- Query:
  - `search` (reference/description/notes)
  - `type`
  - `direction`
  - `method`
  - `date_from`
  - `date_to`
  - `per_page`
- Response: paginated `CashMovement[]`

### POST `/`
- Body:
  - `type` required (`manual_adjustment|income|expense`)
  - `direction` required (`in|out`)
  - `amount` required > 0
  - `method`, `reference_type`, `reference_id`, `reference`, `movement_date`, `description`, `notes` optional
- Backend behavior:
  - creates manual cash movement foundation entry

---

## Dress Categories

Base: `/api/tenant/dress-categories`

Permissions:
- index/show: `dress_categories.view`
- store: `dress_categories.create`
- update: `dress_categories.update`
- delete: `dress_categories.delete`

### GET `/`
- Query:
  - `search` (name)
  - `status`
  - `parent_id`
  - `only_parents` (`true|false`)
  - `only_children` (`true|false`)
  - `per_page`
- Response: paginated `DressCategory[]`

### POST `/`
- Body:
  - `parent_id` optional (null = parent category, non-null = subcategory)
  - `name` required
  - `slug` optional unique
  - `description` optional
  - `status` optional (`active|inactive`)

### GET `/{dressCategory}`
### PUT `/{dressCategory}`
### DELETE `/{dressCategory}`

---

## Dresses

Base: `/api/tenant/dresses`

Permissions:
- index/show: `dresses.view`
- store: `dresses.create`
- update: `dresses.update`
- delete: `dresses.delete`
- inventory movements: `inventory.view`

### GET `/`
- Query:
  - `search` (code, name, color, size, category name, subcategory name)
  - `dress_category_id`
  - `dress_subcategory_id`
  - `branch_id`
  - `status`
  - `color`
  - `size`
  - `per_page`
- Response: paginated `Dress[]`

### POST `/`
- Body:
  - `code` required unique
  - `name` required
  - `dress_category_id` optional
  - `dress_subcategory_id` optional
  - `branch_id` optional
  - `description`, `size`, `color`, `purchase_price`, `rental_price`, `sale_price`, `notes` optional
  - `status` optional (`available|rented|sold|maintenance|unavailable`)
- Response includes:
  - `display_name` = `code - category - subcategory` (from relations)

### GET `/{dress}`
### PUT `/{dress}`
### DELETE `/{dress}` (soft delete)

---

## Inventory Movements

### GET `/api/tenant/dresses/{dress}/inventory-movements`
- Permission: `inventory.view`
- Query:
  - `per_page`
- Response: paginated `InventoryMovement[]`
- Includes:
  - `from_branch_id`, `to_branch_id`, `type`, `quantity`, reason/reference fields

---

## Invoices

Base: `/api/tenant/invoices`

Permissions:
- index/show: `invoices.view`
- store: `invoices.create`
- update: `invoices.update`
- delete: `invoices.delete`

### GET `/`
- Query:
  - `search` (invoice_number)
  - `customer_id`
  - `type` (`rent|sell|tailoring`)
  - `status`
  - `date_from`
  - `date_to`
  - `per_page`
- Response: paginated `Invoice[]`

### POST `/`
- Body:
  - `customer_id` optional
  - `type` required (`rent|sell|tailoring`)
  - `status` optional
  - financial inputs: `discount`, `tax`
  - rent fields: `rent_start_date`, `rent_end_date`, `delivery_date`, `return_date`, `security_deposit`, `security_deposit_status`
  - tailoring fields: `tailoring_due_date`, `tailoring_notes`
  - `notes`
  - `items` required array:
    - `dress_id`, `item_type`, `description`, `quantity`, `unit_price`
  - `initial_payment` optional object
- Backend behavior:
  - auto `invoice_number`
  - calculate subtotal/total/paid/remaining
  - create items
  - create initial payment if provided
  - rent overlap protection when creating confirmed rent invoice

### GET `/{invoice}`
### PUT `/{invoice}`
- supports replacing items and recalculating totals
- blocked if cancelled unless `allow_cancelled_update=true`

### DELETE `/{invoice}`
- soft delete

---

## Invoice Payments

### GET `/api/tenant/invoices/{invoice}/payments`
- Permission: `invoice_payments.view`
- Query: `per_page`
- Response: paginated `InvoicePayment[]`

### POST `/api/tenant/invoices/{invoice}/payments`
- Permission: `invoice_payments.create`
- Body:
  - `amount` required > 0
  - `method` optional (`cash|instapay|vodafone_cash|bank_transfer`)
  - `reference`, `paid_at`, `notes` optional
- Backend behavior:
  - add payment record
  - create cash movement:
    - `type=invoice_payment`
    - `direction=in`
    - `reference_type=invoice_payment`
    - `reference_id=payment.id`
    - `movement_date=paid_at or now`
  - update invoice `paid_amount` and `remaining_amount`
  - status becomes `partially_paid` or `paid`

---

## Tenant Lookups

### GET `/api/tenant/lookups`
- Permission: authenticated tenant user
- Response data keys:
  - `customer_statuses`
  - `dress_statuses`
  - `category_statuses`
  - `expense_statuses`
  - `supplier_statuses`
  - `purchase_order_statuses`
  - `invoice_types`
  - `invoice_statuses`
  - `payment_methods`
  - `security_deposit_statuses`
  - `inventory_movement_types`
  - `cash_movement_types`
  - `cash_movement_directions`
  - `delivery_record_types`
  - `security_deposit_transaction_types`
  - `dress_status_after_return`

---

## Delivery / Return and Security Deposit Cash Integration

- `POST /api/tenant/invoices/{invoice}/security-deposit/deductions` additionally creates cash movement:
  - `type=security_deposit_deduction`
  - `direction=in`
  - `reference_type=security_deposit_transaction`
  - `reference_id=security_deposit_transaction.id`
  - `movement_date=now`

- `POST /api/tenant/suppliers/{supplier}/payments` additionally creates cash movement:
  - `type=supplier_payment`
  - `direction=out`
  - `reference_type=supplier_payment`
  - `reference_id=supplier_payment.id`
  - `movement_date=paid_at or now`

---

## UI Contract Gap Review Round 1 Additions

### Customers (UI additions)

- New/verified fields:
  - `date_of_birth`, `phone2`, `city_id`, `source`
- New filters:
  - `id`, `source`, `date_of_birth_from`, `date_of_birth_to`
- New endpoint:
  - `GET /api/tenant/customers/export` (permission: `customers.export`)
  - CSV attachment with `Content-Disposition`.

### Branches (full tenant CRUD)

Base: `/api/tenant/branches`

- Permissions:
  - `branches.view`, `branches.create`, `branches.update`, `branches.delete`, `branches.export`
- Fields:
  - `branch_code`, `name`, `phone`, `vat_enabled`, `vat_type`, `vat_value`, `currency`, `currency_id`,
    `street`, `building`, `city_id`, `address`, `notes`, `inventory_name`, `image`, `status`
- Endpoints:
  - `GET /`, `POST /`, `GET /{branch}`, `PUT /{branch}`, `DELETE /{branch}`
  - `GET /export` (CSV)
- Filters:
  - `search` (name/code/phone/address), `status`, `city_id`, `currency_id`

### Dresses (availability + exports)

- New/verified fields:
  - `entity_type`, `entity_id`, `breast_size`, `waist_size`, `sleeve_size`, `measurements`,
    `delivery_date`, `days_of_rent`, `occasion_datetime`, `visit_datetime`
- Expanded filters:
  - `id`, `search`, `name`, `code`, `branch_id`, `entity_type`, `entity_id`,
    `category_id`, `subcat_id`, `status`, `created_from`, `created_to`,
    `delivery_date`, `days_of_rent`, `occasion_datetime`, `visit_datetime`
- New endpoints:
  - `GET /api/tenant/dresses/{dress}/order-history`
  - `GET /api/tenant/dresses/available-for-date`
  - `GET /api/tenant/dresses/{dress}/unavailable-days`
  - `GET /api/tenant/dresses/export` (permission: `dresses.export`)

### Invoices (actions + export)

- New endpoints:
  - `POST /api/tenant/invoices/{invoice}/cancel` (permission: `invoices.cancel`)
  - `GET /api/tenant/invoices/export` (permission: `invoices.export`)
- Expanded filters:
  - `search`, `status`, `customer_id`/`client_id`, `branch_id`, `date_from`, `date_to`, `type`
- New/verified fields:
  - `branch_id`, `visit_datetime`, `occasion_datetime`, `days_of_rent`,
    `discount_type`, `discount_value`, `order_notes`

### Payments (standalone foundation)

Base: `/api/tenant/payments`

- Permissions:
  - `payments.view`, `payments.pay`, `payments.cancel`, `payments.export`
- Endpoints:
  - `GET /`
  - `GET /{payment}`
  - `POST /{payment}/pay`
  - `POST /{payment}/cancel`
  - `GET /export` (CSV)
- Supported filters:
  - `search`, `status`, `payment_type`, `branch_id`,
    `customer_id`/`client_id`, `invoice_id`/`order_id`,
    `date_from`, `date_to`, `amount_min`, `amount_max`

### Expenses (workflow actions)

- New/verified fields:
  - `branch_id`, `cashbox_id`, `vendor`, `reference_number`, `status`,
    `approved_by`, `paid_at`, `cancelled_at`, `transaction_id`
- Status values:
  - `pending`, `approved`, `paid`, `cancelled`
- New endpoints:
  - `POST /api/tenant/expenses/{expense}/approve`
  - `POST /api/tenant/expenses/{expense}/cancel`
  - `POST /api/tenant/expenses/{expense}/pay`
  - `GET /api/tenant/expenses/summary`
  - `GET /api/tenant/expenses/export`

### Cashboxes (new minimal module)

Base: `/api/tenant/cashboxes`

- Permissions:
  - `cashboxes.view`, `cashboxes.create`, `cashboxes.update`, `cashboxes.delete`,
    `cashboxes.recalculate`, `cashboxes.export`
- Endpoints:
  - `GET /`, `POST /`, `GET /{cashbox}`, `PUT /{cashbox}`, `DELETE /{cashbox}`
  - `GET /{cashbox}/transactions`
  - `POST /{cashbox}/recalculate`
  - `GET /export`
  - `GET /daily-summary`
- Cash movement additions:
  - `cashbox_id`, `balance_after`, `is_reversed`

### Suppliers / Purchase Orders (UI additions)

- Supplier field added:
  - `code` (nullable unique)
- New endpoints:
  - `GET /api/tenant/suppliers/export`
  - `GET /api/tenant/purchase-orders/export`
  - `POST /api/tenant/purchase-orders/{purchaseOrder}/return`
- Purchase order additions:
  - `branch_id`, `category_id`, `subcategory_id`, `type`, `is_returned`, `returned_at`, `return_notes`
  - aliases in response: `payment_amount`, `remaining_payment`

### Lookups expanded keys

`GET /api/tenant/lookups` now includes:

- `branch_statuses`
- `vat_types`
- `customer_sources`
- `invoice_types`
- `invoice_statuses`
- `payment_statuses`
- `payment_types`
- `expense_statuses`
- `cashbox_statuses`
- `supplier_statuses`
- `purchase_order_statuses`
- `report_periods`
- `dress_statuses`
- `dress_status_after_return`
- `cash_movement_types`
- `cash_movement_directions`

---

## Validation / Error Notes

- Validation errors return `422` with:
  - `success: false`
  - `message`
  - `errors` object keyed by field
- Unauthorized: `401`
- Forbidden: `403`
- Missing tenant workspace: `400`
- Invalid tenant: `404`
