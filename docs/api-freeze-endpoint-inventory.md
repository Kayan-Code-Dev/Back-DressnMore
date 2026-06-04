# API Freeze Endpoint Inventory (Tenant APIs)

Last verified with: `php artisan route:list --path=api/tenant`

## Global tenant API conventions

- Base path: `/api/tenant`
- Standard success envelope:
  - `success`, `message`, `data`, `meta`
- Standard error envelope:
  - `success`, `message`, `errors`
- Authenticated tenant endpoints use middleware stack:
  - `identify.tenant`
  - `check.tenant.subscription`
  - `set.tenant.database`
  - `auth:sanctum`
- Unauthenticated tenant endpoints:
  - `POST /api/tenant/login`
  - `GET /api/tenant/health`
- Required headers:
  - Always: `Accept: application/json`
  - For POST/PUT with JSON body: `Content-Type: application/json`
  - Tenant context: `X-Tenant: <workspace>`
  - Authenticated routes: `Authorization: Bearer <token>`

---

## Module: Auth

### 1) `GET /api/tenant/health`
- Auth required: No
- Headers: `Accept`, `X-Tenant`
- Permission: N/A
- Query params: None
- Request body: None
- Response fields (`data`):
  - `ok`, `timestamp`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Health only, no permissions map dependency; tenant/database identifiers are intentionally omitted.

### 2) `POST /api/tenant/login`
- Auth required: No
- Headers: `Accept`, `Content-Type`, `X-Tenant`
- Permission: N/A
- Query params: None
- Request body:
  - `workspace`, `email`, `password`
- Response fields (`data`):
  - `token`
  - `user` (UserResource)
  - `tenant` (`id`, `name`, `slug`)
  - `permissions` (string[])
  - `plan`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Canonical tenant auth/session bootstrap endpoint.

### 3) `POST /api/tenant/logout`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: none (auth only)
- Query params: None
- Request body: None
- Response fields (`data`): `null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Invalidates current access token.

### 4) `GET /api/tenant/me`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: none (auth only)
- Query params: None
- Request body: None
- Response fields (`data`):
  - `user` (UserResource)
  - `tenant` (`id`, `name`, `slug`)
  - `permissions`
  - `plan`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Reused by frontend profile screen; no dedicated `/settings/profile` endpoint exists.

---

## Module: Lookups

### 5) `GET /api/tenant/lookups`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: none (auth only)
- Query params: None
- Request body: None
- Response fields (`data`):
  - Lookup groups documented in `docs/lookups-contract.md`
- Pagination meta: N/A
- Lookups used: Source endpoint itself
- Export behavior: N/A
- Notes/deferred: Single canonical lookup source for frontend options.

---

## Module: Dashboard

- Endpoint status: **missing/deferred**
- Existing route: None under `/api/tenant/dashboard*`
- Notes/deferred:
  - Permission `dashboard.view` exists in seeder.
  - Dashboard API remains deferred until product-level KPI contract is finalized.

---

## Module: Customers

### 6) `GET /api/tenant/customers`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `customers.view`
- Query params:
  - `search`, `id`, `source`, `status`, `date_of_birth_from`, `date_of_birth_to`, `page`, `per_page`
- Request body: None
- Response fields (`data[]`, CustomerResource):
  - `id`, `name`, `date_of_birth`, `phone`, `phone2`, `whatsapp`, `email`, `address`, `city_id`, `national_id`, `source`, `notes`, `status`, timestamps
- Pagination meta: Yes (`current_page`, `per_page`, `total`, `last_page`)
- Lookups used: `customer_statuses`, `customer_sources`
- Export behavior: N/A
- Notes/deferred: `address_id` filter deferred (no address module/table).

### 7) `POST /api/tenant/customers`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `customers.create`
- Query params: None
- Request body:
  - `name` (required)
  - optional: `date_of_birth`, `phone`, `phone2`, `whatsapp`, `email`, `address`, `city_id`, `national_id`, `source`, `notes`, `status`
- Response fields: CustomerResource
- Pagination meta: N/A
- Lookups used: `customer_statuses`, `customer_sources`
- Export behavior: N/A
- Notes/deferred: None.

### 8) `GET /api/tenant/customers/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `customers.export`
- Query params:
  - Same filters as list endpoint
- Request body: None
- Response fields:
  - Streamed CSV body
- Pagination meta: N/A
- Lookups used: same as list filter UI
- Export behavior:
  - `Content-Disposition: attachment; filename=customers.csv`
  - `Content-Type: text/csv; charset=UTF-8`
- Notes/deferred: XLSX not implemented; CSV is freeze baseline.

### 9) `GET /api/tenant/customers/{customer}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `customers.view`
- Query params: None
- Request body: None
- Response fields: CustomerResource
- Pagination meta: N/A
- Lookups used: `customer_statuses`, `customer_sources`
- Export behavior: N/A
- Notes/deferred: None.

### 10) `PUT /api/tenant/customers/{customer}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `customers.update`
- Query params: None
- Request body: Same shape as create
- Response fields: CustomerResource
- Pagination meta: N/A
- Lookups used: `customer_statuses`, `customer_sources`
- Export behavior: N/A
- Notes/deferred: None.

### 11) `DELETE /api/tenant/customers/{customer}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `customers.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

---

## Module: Dress Categories

### 12) `GET /api/tenant/dress-categories`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dress_categories.view`
- Query params:
  - `search`, `status`, `parent_id`, `only_parents`, `only_children`, `page`, `per_page`
- Request body: None
- Response fields (DressCategoryResource):
  - `id`, `parent_id`, `name`, `slug`, `description`, `status`, `parent`, `children`, timestamps
- Pagination meta: Yes
- Lookups used: `category_statuses`
- Export behavior: N/A
- Notes/deferred: Subcategories handled in same endpoint by `parent_id`.

### 13) `POST /api/tenant/dress-categories`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `dress_categories.create`
- Query params: None
- Request body:
  - `name` required
  - optional: `parent_id`, `slug`, `description`, `status`
- Response fields: DressCategoryResource
- Pagination meta: N/A
- Lookups used: `category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 14) `GET /api/tenant/dress-categories/{dressCategory}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dress_categories.view`
- Query params: None
- Request body: None
- Response fields: DressCategoryResource
- Pagination meta: N/A
- Lookups used: `category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 15) `PUT /api/tenant/dress-categories/{dressCategory}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `dress_categories.update`
- Query params: None
- Request body: same as create
- Response fields: DressCategoryResource
- Pagination meta: N/A
- Lookups used: `category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 16) `DELETE /api/tenant/dress-categories/{dressCategory}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dress_categories.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

---

## Module: Subcategories

- Route status: uses same routes as Dress Categories.
- Contract:
  - Create/list subcategories by setting/filtering `parent_id`.
  - No separate `/subcategories` endpoint.
- Notes/deferred:
  - Separate endpoint intentionally deferred to avoid duplicate contract surface.

---

## Module: Branches

### 17) `GET /api/tenant/branches`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `branches.view`
- Query params:
  - `search`, `status`, `city_id`, `currency_id`, `page`, `per_page`
- Request body: None
- Response fields (BranchResource):
  - `id`, `branch_code`, `name`, `code`, `phone`, `vat_enabled`, `vat_type`, `vat_value`, `currency`, `currency_id`, `street`, `building`, `city_id`, `address`, `notes`, `inventory_name`, `image`, `status`, timestamps
- Pagination meta: Yes
- Lookups used: `branch_statuses`, `vat_types`
- Export behavior: N/A
- Notes/deferred: Currency/city are scalar IDs (no dedicated lookup table endpoint yet).

### 18) `POST /api/tenant/branches`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `branches.create`
- Query params: None
- Request body:
  - `name` required
  - optional: `branch_code`, `phone`, `vat_enabled`, `vat_type`, `vat_value`, `currency`, `currency_id`, `street`, `building`, `city_id`, `address`, `notes`, `inventory_name`, `image`, `status`
- Response fields: BranchResource
- Pagination meta: N/A
- Lookups used: `branch_statuses`, `vat_types`
- Export behavior: N/A
- Notes/deferred: `code` mirrors `branch_code` for backward compatibility.

### 19) `GET /api/tenant/branches/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `branches.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: same as list filters
- Export behavior: `branches.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 20) `GET /api/tenant/branches/{branch}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `branches.view`
- Query params: None
- Request body: None
- Response fields: BranchResource
- Pagination meta: N/A
- Lookups used: `branch_statuses`, `vat_types`
- Export behavior: N/A
- Notes/deferred: None.

### 21) `PUT /api/tenant/branches/{branch}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `branches.update`
- Query params: None
- Request body: same as create
- Response fields: BranchResource
- Pagination meta: N/A
- Lookups used: `branch_statuses`, `vat_types`
- Export behavior: N/A
- Notes/deferred: None.

### 22) `DELETE /api/tenant/branches/{branch}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `branches.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

---

## Module: Dresses

### 23) `GET /api/tenant/dresses`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.view`
- Query params:
  - `id`, `search`, `name`, `code`, `dress_category_id`, `dress_subcategory_id`, `category_id`, `subcat_id`, `branch_id`, `entity_type`, `entity_id`, `status`, `color`, `size`, `created_from`, `created_to`, `delivery_date`, `days_of_rent`, `occasion_datetime`, `visit_datetime`, `page`, `per_page`
- Request body: None
- Response fields (DressResource):
  - `id`, `dress_category_id`, `dress_subcategory_id`, `branch_id`, `entity_type`, `entity_id`, `code`, `name`, `description`, `size`, `breast_size`, `waist_size`, `sleeve_size`, `measurements`, `color`, `purchase_price`, `rental_price`, `sale_price`, `delivery_date`, `days_of_rent`, `occasion_datetime`, `visit_datetime`, `status`, `notes`, `display_name`, relation snapshots
- Pagination meta: Yes
- Lookups used: `dress_statuses`
- Export behavior: N/A
- Notes/deferred: `inventory_id` filter deferred (no inventory entity model endpoint).

### 24) `POST /api/tenant/dresses`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `dresses.create`
- Query params: None
- Request body:
  - required: `code`, `name`
  - optional: category/subcategory/branch/entity fields, sizing, prices, scheduling, `status`, `notes`
- Response fields: DressResource
- Pagination meta: N/A
- Lookups used: `dress_statuses`
- Export behavior: N/A
- Notes/deferred: Inventory movement `created` auto-recorded.

### 25) `GET /api/tenant/dresses/available-for-date`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.view`
- Query params:
  - `date` or (`start_date`, `end_date`), optional `branch_id`, `category_id`, `subcat_id`, `status`, `page`, `per_page`
- Request body: None
- Response fields: paginated DressResource
- Pagination meta: Yes
- Lookups used: `dress_statuses`
- Export behavior: N/A
- Notes/deferred: Uses rent-invoice overlap blocking rules.

### 26) `GET /api/tenant/dresses/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.export`
- Query params: same filter surface as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: same as list filters
- Export behavior: `dresses.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 27) `GET /api/tenant/dresses/{dress}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.view`
- Query params: None
- Request body: None
- Response fields: DressResource
- Pagination meta: N/A
- Lookups used: `dress_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 28) `PUT /api/tenant/dresses/{dress}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `dresses.update`
- Query params: None
- Request body: same as create
- Response fields: DressResource
- Pagination meta: N/A
- Lookups used: `dress_statuses`
- Export behavior: N/A
- Notes/deferred: Status changes auto-record inventory movements.

### 29) `DELETE /api/tenant/dresses/{dress}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

### 30) `GET /api/tenant/dresses/{dress}/inventory-movements`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `inventory.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: InventoryMovementResource paginated
- Pagination meta: Yes
- Lookups used: `inventory_movement_types`
- Export behavior: N/A
- Notes/deferred: Dedicated inventory CRUD endpoints not implemented.

### 31) `GET /api/tenant/dresses/{dress}/order-history`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields (DressOrderHistoryResource):
  - `id`, `invoice_id`, `invoice_number`, `invoice_type`, `invoice_status`, `customer_id`, item amounts, rent/delivery dates
- Pagination meta: Yes
- Lookups used: `invoice_types`, `invoice_statuses`
- Export behavior: N/A
- Notes/deferred: Read-only historic mapping from invoice items.

### 32) `GET /api/tenant/dresses/{dress}/unavailable-days`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `dresses.view`
- Query params: None
- Request body: None
- Response fields:
  - `ranges[]` (`invoice_id`, `invoice_number`, `start_date`, `end_date`)
  - `days[]` (ISO dates)
- Pagination meta: N/A
- Lookups used: `invoice_statuses`
- Export behavior: N/A
- Notes/deferred: Rent-booked day expansion only.

---

## Module: Inventory Movements

- Contract endpoint inventory is represented by:
  - `GET /api/tenant/dresses/{dress}/inventory-movements` (see endpoint #30)
- Notes/deferred:
  - No standalone `/api/tenant/inventory-movements` endpoint yet.
  - Foundation is event-driven via dress/invoice/delivery flows.

---

## Module: Invoices

### 33) `GET /api/tenant/invoices`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoices.view`
- Query params:
  - `search`, `customer_id`, `client_id`, `branch_id`, `type`, `status`, `date_from`, `date_to`, `page`, `per_page`
- Request body: None
- Response fields (InvoiceResource):
  - core identifiers/financials/status/type
  - rent/tailoring/workflow fields
  - UI fields: `branch_id`, `visit_datetime`, `occasion_datetime`, `days_of_rent`, `discount_type`, `discount_value`, `order_notes`
- Pagination meta: Yes
- Lookups used: `invoice_types`, `invoice_statuses`, `security_deposit_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: `employee_id` filtering deferred (no employee module).

### 34) `POST /api/tenant/invoices`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `invoices.create`
- Query params: None
- Request body:
  - required: `type`, `items[]`
  - optional: `customer_id`/`client_id`, `branch_id`, financials, rent dates, deposit fields, tailoring fields, UI datetime fields, `order_notes`, `notes`, `initial_payment`
- Response fields: InvoiceResource
- Pagination meta: N/A
- Lookups used: `invoice_types`, `invoice_statuses`, `security_deposit_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: Backend owns totals/status calculations.

### 35) `GET /api/tenant/invoices/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoices.export`
- Query params: same list filters
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: same as list
- Export behavior: `invoices.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 36) `GET /api/tenant/invoices/{invoice}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoices.view`
- Query params: None
- Request body: None
- Response fields: InvoiceResource (+ items/payments if loaded)
- Pagination meta: N/A
- Lookups used: same as list
- Export behavior: N/A
- Notes/deferred: None.

### 37) `PUT /api/tenant/invoices/{invoice}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `invoices.update`
- Query params: None
- Request body: same shape as create (+ `allow_cancelled_update`)
- Response fields: InvoiceResource
- Pagination meta: N/A
- Lookups used: same as create
- Export behavior: N/A
- Notes/deferred: Cancelled update blocked unless override flag provided.

### 38) `DELETE /api/tenant/invoices/{invoice}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoices.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

### 39) `POST /api/tenant/invoices/{invoice}/cancel`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoices.cancel`
- Query params: None
- Request body: None
- Response fields: InvoiceResource (status cancelled)
- Pagination meta: N/A
- Lookups used: `invoice_statuses`
- Export behavior: N/A
- Notes/deferred: Delivered/returned invoices cannot be cancelled.

---

## Module: Invoice Payments (nested)

### 40) `GET /api/tenant/invoices/{invoice}/payments`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoice_payments.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: InvoicePaymentResource paginated
- Pagination meta: Yes
- Lookups used: `payment_methods`, `payment_statuses`, `payment_types`
- Export behavior: N/A
- Notes/deferred: Nested listing by invoice context.

### 41) `POST /api/tenant/invoices/{invoice}/payments`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `invoice_payments.create`
- Query params: None
- Request body:
  - `amount` required
  - optional: `method`, `reference`, `paid_at`, `notes`
- Response fields: InvoiceResource (invoice refreshed financials)
- Pagination meta: N/A
- Lookups used: `payment_methods`
- Export behavior: N/A
- Notes/deferred: Also creates `cash_movements` entry (`invoice_payment`, `in`).

---

## Module: Standalone Payments

### 42) `GET /api/tenant/payments`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `payments.view`
- Query params:
  - `search`, `status`, `payment_type`, `branch_id`, `customer_id`, `client_id`, `invoice_id`, `order_id`, `date_from`, `date_to`, `amount_min`, `amount_max`, `page`, `per_page`
- Request body: None
- Response fields: InvoicePaymentResource paginated
- Pagination meta: Yes
- Lookups used: `payment_statuses`, `payment_types`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: `employee_id` filter deferred.

### 43) `GET /api/tenant/payments/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `payments.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: same as list
- Export behavior: `payments.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 44) `GET /api/tenant/payments/{payment}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `payments.view`
- Query params: None
- Request body: None
- Response fields: InvoicePaymentResource
- Pagination meta: N/A
- Lookups used: `payment_statuses`, `payment_types`
- Export behavior: N/A
- Notes/deferred: None.

### 45) `POST /api/tenant/payments/{payment}/pay`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `payments.pay`
- Query params: None
- Request body: None
- Response fields: InvoicePaymentResource
- Pagination meta: N/A
- Lookups used: `payment_statuses`
- Export behavior: N/A
- Notes/deferred: Creates missing cash movement if payment was pending.

### 46) `POST /api/tenant/payments/{payment}/cancel`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `payments.cancel`
- Query params: None
- Request body: None
- Response fields: InvoicePaymentResource
- Pagination meta: N/A
- Lookups used: `payment_statuses`
- Export behavior: N/A
- Notes/deferred: Marks related cash movement as reversed.

---

## Module: Deliveries

### 47) `POST /api/tenant/invoices/{invoice}/deliver`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `invoice_delivery.deliver`
- Query params: None
- Request body:
  - optional: `delivered_at`, `receiver_name`, `receiver_phone`, `notes`
- Response fields: InvoiceResource
- Pagination meta: N/A
- Lookups used: `delivery_record_types`, `invoice_statuses`
- Export behavior: N/A
- Notes/deferred: Updates invoice/dress/inventory states by invoice type.

### 48) `GET /api/tenant/invoices/{invoice}/delivery-records`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `invoice_delivery.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: DeliveryRecordResource paginated
- Pagination meta: Yes
- Lookups used: `delivery_record_types`
- Export behavior: N/A
- Notes/deferred: No export endpoint yet (deferred).

---

## Module: Returns

### 49) `POST /api/tenant/invoices/{invoice}/return`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `invoice_delivery.return`
- Query params: None
- Request body:
  - optional: `returned_at`, `notes`, `dress_status_after_return` (`available|maintenance`)
- Response fields: InvoiceResource
- Pagination meta: N/A
- Lookups used: `dress_status_after_return`, `invoice_statuses`
- Export behavior: N/A
- Notes/deferred: Rent-only return flow.

---

## Module: Security Deposits

### 50) `POST /api/tenant/invoices/{invoice}/security-deposit/deductions`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `security_deposit.deduct`
- Query params: None
- Request body:
  - `amount` required
  - optional: `reason`, `notes`
- Response fields: InvoiceResource
- Pagination meta: N/A
- Lookups used: `security_deposit_statuses`, `security_deposit_transaction_types`
- Export behavior: N/A
- Notes/deferred: Creates `security_deposit_deduction` cash movement.

### 51) `GET /api/tenant/invoices/{invoice}/security-deposit/transactions`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `security_deposit.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: SecurityDepositTransactionResource paginated
- Pagination meta: Yes
- Lookups used: `security_deposit_transaction_types`
- Export behavior: N/A
- Notes/deferred: No export endpoint yet (deferred).

---

## Module: Expense Categories

### 52) `GET /api/tenant/expense-categories`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expense_categories.view`
- Query params: `search`, `status`, `page`, `per_page`
- Request body: None
- Response fields: ExpenseCategoryResource paginated
- Pagination meta: Yes
- Lookups used: `expense_category_statuses`
- Export behavior: N/A
- Notes/deferred: No export endpoint yet.

### 53) `POST /api/tenant/expense-categories`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expense_categories.create`
- Query params: None
- Request body:
  - `name` required
  - optional: `slug`, `description`, `status`
- Response fields: ExpenseCategoryResource
- Pagination meta: N/A
- Lookups used: `expense_category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 54) `GET /api/tenant/expense-categories/{expenseCategory}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expense_categories.view`
- Query params: None
- Request body: None
- Response fields: ExpenseCategoryResource
- Pagination meta: N/A
- Lookups used: `expense_category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 55) `PUT /api/tenant/expense-categories/{expenseCategory}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expense_categories.update`
- Query params: None
- Request body: same as create
- Response fields: ExpenseCategoryResource
- Pagination meta: N/A
- Lookups used: `expense_category_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 56) `DELETE /api/tenant/expense-categories/{expenseCategory}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expense_categories.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

---

## Module: Expenses

### 57) `GET /api/tenant/expenses`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expenses.view`
- Query params:
  - `search`, `expense_category_id`, `branch_id`, `cashbox_id`, `status`, `method`, `date_from`, `date_to`, `page`, `per_page`
- Request body: None
- Response fields: ExpenseResource paginated
- Pagination meta: Yes
- Lookups used: `expense_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: None.

### 58) `POST /api/tenant/expenses`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expenses.create`
- Query params: None
- Request body:
  - required: `amount`, `expense_date`
  - optional: `expense_category_id`, `branch_id`, `cashbox_id`, `status`, `method`, `vendor`, `reference`, `reference_number`, `description`, `notes`, `transaction_id`
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: Cash movement auto-created only when status resolves to paid.

### 59) `GET /api/tenant/expenses/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expenses.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: same as list
- Export behavior: `expenses.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 60) `GET /api/tenant/expenses/summary`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expenses.summary`
- Query params: `expense_category_id`, `date_from`, `date_to`
- Request body: None
- Response fields:
  - `total_amount`, `pending_amount`, `approved_amount`, `paid_amount`, `cancelled_amount`, `by_category[]`
- Pagination meta: N/A
- Lookups used: `expense_statuses`
- Export behavior: N/A
- Notes/deferred: Aggregation foundation only, not reporting module.

### 61) `GET /api/tenant/expenses/{expense}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expenses.view`
- Query params: None
- Request body: None
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 62) `PUT /api/tenant/expenses/{expense}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expenses.update`
- Query params: None
- Request body: same shape as create + optional `allow_financial_edit`
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: Paid financial fields locked unless explicit override.

### 63) `DELETE /api/tenant/expenses/{expense}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `expenses.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft deletes expense and linked movement.

### 64) `POST /api/tenant/expenses/{expense}/approve`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expenses.approve`
- Query params: None
- Request body: none required
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`
- Export behavior: N/A
- Notes/deferred: Cancelled expense cannot be approved.

### 65) `POST /api/tenant/expenses/{expense}/cancel`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expenses.cancel`
- Query params: None
- Request body: optional `notes`
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`
- Export behavior: N/A
- Notes/deferred: Paid expense cancellation blocked.

### 66) `POST /api/tenant/expenses/{expense}/pay`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `expenses.pay`
- Query params: None
- Request body: optional `cashbox_id`, `method`, `paid_at`, `transaction_id`, `notes`
- Response fields: ExpenseResource
- Pagination meta: N/A
- Lookups used: `expense_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: Creates/syncs outgoing cash movement.

---

## Module: Cash Movements

### 67) `GET /api/tenant/cash-movements`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cash_movements.view`
- Query params:
  - `search`, `type`, `direction`, `method`, `cashbox_id`, `is_reversed`, `date_from`, `date_to`, `page`, `per_page`
- Request body: None
- Response fields: CashMovementResource paginated
- Pagination meta: Yes
- Lookups used: `cash_movement_types`, `cash_movement_directions`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: No dedicated export endpoint yet.

### 68) `POST /api/tenant/cash-movements`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `cash_movements.create`
- Query params: None
- Request body:
  - required: `type`, `direction`, `amount`
  - optional: `method`, `cashbox_id`, `reference_type`, `reference_id`, `reference`, `movement_date`, `description`, `notes`
- Response fields: CashMovementResource
- Pagination meta: N/A
- Lookups used: `cash_movement_types`, `cash_movement_directions`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: Manual adjustment/income/expense validation enforced.

---

## Module: Cashboxes

### 69) `GET /api/tenant/cashboxes`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.view`
- Query params: `search`, `branch_id`, `is_active`, `page`, `per_page`
- Request body: None
- Response fields: CashboxResource paginated
- Pagination meta: Yes
- Lookups used: `cashbox_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 70) `POST /api/tenant/cashboxes`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.create`
- Query params: None
- Request body:
  - required: `name`
  - optional: `branch_id`, `initial_balance`, `description`, `is_active`
- Response fields: CashboxResource
- Pagination meta: N/A
- Lookups used: `cashbox_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 71) `GET /api/tenant/cashboxes/daily-summary`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.view`
- Query params: `cashbox_id`, `branch_id`, `date_from`, `date_to`
- Request body: None
- Response fields: `total_in`, `total_out`, `net`
- Pagination meta: N/A
- Lookups used: `cash_movement_directions`
- Export behavior: N/A
- Notes/deferred: Summary only; no time-series reporting.

### 72) `GET /api/tenant/cashboxes/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: `cashbox_statuses`
- Export behavior: `cashboxes.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 73) `GET /api/tenant/cashboxes/{cashbox}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.view`
- Query params: None
- Request body: None
- Response fields: CashboxResource
- Pagination meta: N/A
- Lookups used: `cashbox_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 74) `PUT /api/tenant/cashboxes/{cashbox}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.update`
- Query params: None
- Request body: same as create
- Response fields: CashboxResource
- Pagination meta: N/A
- Lookups used: `cashbox_statuses`
- Export behavior: N/A
- Notes/deferred: Recalculates balances after update.

### 75) `DELETE /api/tenant/cashboxes/{cashbox}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

### 76) `POST /api/tenant/cashboxes/{cashbox}/recalculate`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.recalculate`
- Query params: None
- Request body: None
- Response fields: CashboxResource
- Pagination meta: N/A
- Lookups used: `cash_movement_directions`
- Export behavior: N/A
- Notes/deferred: Rebuilds current balance from non-reversed movements.

### 77) `GET /api/tenant/cashboxes/{cashbox}/transactions`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `cashboxes.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: CashMovementResource paginated
- Pagination meta: Yes
- Lookups used: `cash_movement_types`, `cash_movement_directions`
- Export behavior: N/A
- Notes/deferred: No dedicated export endpoint for transactions list.

---

## Module: Suppliers

### 78) `GET /api/tenant/suppliers`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `suppliers.view`
- Query params: `search`, `status`, `page`, `per_page`
- Request body: None
- Response fields: SupplierResource paginated
- Pagination meta: Yes
- Lookups used: `supplier_statuses`
- Export behavior: N/A
- Notes/deferred: Includes summary balances.

### 79) `POST /api/tenant/suppliers`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `suppliers.create`
- Query params: None
- Request body:
  - required: `name`
  - optional: `code`, `phone`, `whatsapp`, `email`, `address`, `tax_number`, `opening_balance`, `notes`, `status`
- Response fields: SupplierResource
- Pagination meta: N/A
- Lookups used: `supplier_statuses`, `payment_methods`
- Export behavior: N/A
- Notes/deferred: `code` nullable unique per tenant DB.

### 80) `GET /api/tenant/suppliers/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `suppliers.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: `supplier_statuses`
- Export behavior: `suppliers.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 81) `GET /api/tenant/suppliers/{supplier}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `suppliers.view`
- Query params: None
- Request body: None
- Response fields: SupplierResource
- Pagination meta: N/A
- Lookups used: `supplier_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 82) `PUT /api/tenant/suppliers/{supplier}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `suppliers.update`
- Query params: None
- Request body: same as create
- Response fields: SupplierResource
- Pagination meta: N/A
- Lookups used: `supplier_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 83) `DELETE /api/tenant/suppliers/{supplier}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `suppliers.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

---

## Module: Purchase Orders

### 84) `GET /api/tenant/purchase-orders`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.view`
- Query params:
  - `search`, `supplier_id`, `branch_id`, `category_id`, `subcategory_id`, `type`, `status`, `is_returned`, `date_from`, `date_to`, `page`, `per_page`
- Request body: None
- Response fields: PurchaseOrderResource paginated
- Pagination meta: Yes
- Lookups used: `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 85) `POST /api/tenant/purchase-orders`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.create`
- Query params: None
- Request body:
  - required: `supplier_id`, `items[]`
  - optional: `branch_id`, `category_id`, `subcategory_id`, `type`, `status`, `discount`, `tax`, `order_date`, `notes`
- Response fields: PurchaseOrderResource
- Pagination meta: N/A
- Lookups used: `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: Number generation and totals/status are backend-controlled.

### 86) `GET /api/tenant/purchase-orders/export`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.export`
- Query params: same as list
- Request body: None
- Response fields: CSV stream
- Pagination meta: N/A
- Lookups used: `purchase_order_statuses`
- Export behavior: `purchase-orders.csv` attachment
- Notes/deferred: CSV baseline freeze format.

### 87) `GET /api/tenant/purchase-orders/{purchaseOrder}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.view`
- Query params: None
- Request body: None
- Response fields: PurchaseOrderResource
- Pagination meta: N/A
- Lookups used: `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 88) `PUT /api/tenant/purchase-orders/{purchaseOrder}`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.update`
- Query params: None
- Request body: same shape as create
- Response fields: PurchaseOrderResource
- Pagination meta: N/A
- Lookups used: `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: None.

### 89) `DELETE /api/tenant/purchase-orders/{purchaseOrder}`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.delete`
- Query params: None
- Request body: None
- Response fields: `data: null`
- Pagination meta: N/A
- Lookups used: None
- Export behavior: N/A
- Notes/deferred: Soft delete.

### 90) `GET /api/tenant/purchase-orders/{purchaseOrder}/payments`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `supplier_payments.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: SupplierPaymentResource paginated
- Pagination meta: Yes
- Lookups used: `payment_methods`
- Export behavior: N/A
- Notes/deferred: Optional foundation route for PO payment tab.

### 91) `POST /api/tenant/purchase-orders/{purchaseOrder}/return`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `purchase_orders.return`
- Query params: None
- Request body: optional `returned_at`, `return_notes`
- Response fields: PurchaseOrderResource
- Pagination meta: N/A
- Lookups used: `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: Complex stock reversal intentionally deferred.

---

## Module: Supplier Payments

### 92) `GET /api/tenant/suppliers/{supplier}/payments`
- Auth required: Yes
- Headers: `Accept`, `Authorization`, `X-Tenant`
- Permission: `supplier_payments.view`
- Query params: `page`, `per_page`
- Request body: None
- Response fields: SupplierPaymentResource paginated
- Pagination meta: Yes
- Lookups used: `payment_methods`
- Export behavior: N/A
- Notes/deferred: No dedicated export endpoint yet.

### 93) `POST /api/tenant/suppliers/{supplier}/payments`
- Auth required: Yes
- Headers: `Accept`, `Content-Type`, `Authorization`, `X-Tenant`
- Permission: `supplier_payments.create`
- Query params: None
- Request body:
  - required: `amount`
  - optional: `purchase_order_id`, `method`, `reference`, `paid_at`, `notes`
- Response fields: SupplierPaymentResource
- Pagination meta: N/A
- Lookups used: `payment_methods`, `purchase_order_statuses`
- Export behavior: N/A
- Notes/deferred: Creates outgoing supplier-payment cash movement.

---

## Module: Settings / Profile

- Profile endpoint status:
  - Available via `GET /api/tenant/me` (see endpoint #4)
- Settings endpoint status:
  - **missing/deferred** (no `/api/tenant/settings*` route)
- Notes/deferred:
  - Permission `settings.manage` exists but no tenant settings API route is currently exposed.

---

## Route-to-doc validation result

- Documented tenant endpoints: **93**
- `php artisan route:list --path=api/tenant` endpoints found: **93**
- Missing documented routes in code: **0**
- Deferred/missing modules explicitly tracked:
  - dashboard API
  - dedicated settings API
  - separate subcategories endpoint (uses dress-categories with `parent_id`)
