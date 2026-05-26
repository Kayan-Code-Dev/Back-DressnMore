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
  - `tenant_name`
  - `tenant_slug`
  - `tenant_database_name`
  - `tenant_database_connection`
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
