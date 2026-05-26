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

Current state:
- Central provisioning tables exist (`tenants`, `tenant_provisioning_logs`).
- Operational provisioning endpoint is intentionally not exposed in current route contract.
- Frontend should not call a provisioning API yet.

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
  - `invoice_types`
  - `invoice_statuses`
  - `payment_methods`
  - `security_deposit_statuses`
  - `inventory_movement_types`

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
