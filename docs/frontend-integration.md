# Frontend Integration Guide

This guide maps the backend contract to a stable frontend integration strategy.

## 1) Required Headers

For tenant-protected requests:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
X-Tenant: <workspace-slug>
```

For platform-protected requests:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
```

## 2) Authorization Flow

- Platform login:
  - `POST /api/platform/login`
- Tenant login:
  - `POST /api/tenant/login` with `workspace`
- Save token in secure storage (memory + refresh-safe storage if needed).
- Inject token in API client interceptor.
- Platform tenant provisioning APIs are available for admin panel:
  - `GET /api/platform/tenants`
  - `POST /api/platform/tenants`
  - `POST /api/platform/tenants/{tenant}/suspend`
  - `POST /api/platform/tenants/{tenant}/activate`
  - `POST /api/platform/tenants/{tenant}/renew`

## 3) X-Tenant Usage

- Always set `X-Tenant` for tenant routes (except tenant login where `workspace` body is also required).
- Recommended to keep current workspace in global state/store.
- On workspace switch, clear cached tenant data and invalidate related queries.

## 4) Unified API Envelope Types

```ts
export type ApiSuccess<T> = {
  success: true;
  message: string;
  data: T;
  meta: Record<string, unknown>;
};

export type ApiError = {
  success: false;
  message: string;
  errors: Record<string, string[] | string | unknown>;
};
```

Pagination:

```ts
export type ApiPaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};
```

## 5) Validation Error Format

Validation responses return:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

Frontend recommendation:
- normalize into `{ field: firstMessage }`
- show global fallback message from `message`.

## 6) Lookup Endpoint Usage

- Endpoint: `GET /api/tenant/lookups`
- Use once on app boot and cache in memory (or query cache).
- Provides option arrays for:
  - statuses
  - expense statuses
  - supplier statuses
  - purchase order statuses
  - invoice types/statuses
  - payment methods
  - inventory movement types
  - cash movement types/directions
  - delivery/security deposit workflow values

Use this endpoint as single source for dropdowns/radio/select options to avoid hardcoded frontend strings.

## 7) Recommended Frontend Structure

```text
src/services/api/client.ts
src/services/auth.service.ts
src/services/customers.service.ts
src/services/branches.service.ts
src/services/dresses.service.ts
src/services/invoices.service.ts
src/services/payments.service.ts
src/services/expenses.service.ts
src/services/cashboxes.service.ts
src/services/cash-movements.service.ts
src/services/suppliers.service.ts
src/services/purchase-orders.service.ts
src/services/lookups.service.ts

src/types/api.ts
src/types/customer.ts
src/types/branch.ts
src/types/dress.ts
src/types/invoice.ts
src/types/payment.ts
src/types/expense.ts
src/types/cashbox.ts
src/types/cash-movement.ts
src/types/supplier.ts
src/types/purchase-order.ts
src/types/lookups.ts
```

## 8) Suggested Service Responsibilities

- `client.ts`
  - base URL
  - request/response interceptors
  - attach token + tenant header
  - normalize API errors

- `auth.service.ts`
  - platform login/logout/me
  - tenant login/logout/me

- `customers.service.ts`
  - list/create/show/update/delete customers
  - export customers CSV (`GET /api/tenant/customers/export`)

- `branches.service.ts`
  - branch CRUD + export

- `dresses.service.ts`
  - categories CRUD
  - dresses CRUD + export
  - available-for-date
  - unavailable-days
  - order-history
  - inventory movement list

- `invoices.service.ts`
  - invoices CRUD + cancel + export
  - invoice payments list/create

- `payments.service.ts`
  - standalone payments list/show/pay/cancel/export

- `lookups.service.ts`
  - fetch and cache lookups

- `expenses.service.ts`
  - expense categories CRUD
  - expenses CRUD
  - expense approve/cancel/pay/summary/export

- `cashboxes.service.ts`
  - cashboxes CRUD
  - cashbox transactions list
  - cashbox recalculate
  - cashbox export
  - daily summary

- `cash-movements.service.ts`
  - list cash movements
  - create manual cash movement

- `suppliers.service.ts`
  - suppliers CRUD
  - supplier payments list/create per supplier

- `purchase-orders.service.ts`
  - purchase orders CRUD
  - optional purchase order payments list

## 9) Suggested Type Shapes

- `customer.ts`
  - `Customer`, `CustomerPayload`
- `branch.ts`
  - `Branch`, `BranchPayload`
- `dress.ts`
  - `DressCategory`, `Dress`, `InventoryMovement`, `Branch`
  - include `display_name` for dress list/details UI
- `invoice.ts`
  - `Invoice`, `InvoiceItem`, `InvoicePayment`
  - `InvoiceItem` should display:
    - `dress_display_name`
    - `dress_code`, `dress_category`, `dress_subcategory`
- `expense.ts`
  - `ExpenseCategory`, `Expense`
- `payment.ts`
  - `StandalonePayment`
- `cashbox.ts`
  - `Cashbox`, `CashboxDailySummary`
- `cash-movement.ts`
  - `CashMovement`
- `supplier.ts`
  - `Supplier`, `SupplierPayment`
- `purchase-order.ts`
  - `PurchaseOrder`, `PurchaseOrderItem`
- `lookups.ts`
  - `LookupOption { value: string; label: string }`
  - `LookupsResponse`

## 10) Integration Notes by Module

- Customers:
  - list endpoints return `data: Customer[]` + pagination in `meta`.
  - UI fields supported: `date_of_birth`, `phone2`, `source`, `city_id`
  - filters supported: `id`, `source`, `date_of_birth_from`, `date_of_birth_to`

- Dress Categories:
  - parent/child hierarchy in one table.
  - filters: `parent_id`, `only_parents`, `only_children`.

- Dresses:
  - preserve separate fields:
    - `code`
    - `dress_category_id`
    - `dress_subcategory_id`
    - `entity_type`, `entity_id`, `measurements`
  - UI display name should use backend `display_name`.
  - availability UI uses:
    - `GET /api/tenant/dresses/available-for-date`
    - `GET /api/tenant/dresses/{dress}/unavailable-days`
    - `GET /api/tenant/dresses/{dress}/order-history`

- Invoices:
  - totals are backend-calculated; frontend should not override.
  - rent overlap validation can return 422 with `rent_period` error key.
  - adding invoice payment also writes an `invoice_payment` cash movement.
  - cancel action route: `POST /api/tenant/invoices/{invoice}/cancel`
  - invoice export route: `GET /api/tenant/invoices/export`
  - supports `client_id` alias for `customer_id`

- Expense Categories:
  - supports search by `name` and status filter.

- Expenses:
  - workflow statuses: `pending|approved|paid|cancelled`
  - create/update/delete keeps linked `expense` cash movement in sync for paid expenses.
  - action routes: approve/cancel/pay.
  - filters supported: category, method, date range, status, search fields.
  - summary/export endpoints available.

- Cash Movements:
  - manual entries support `manual_adjustment`, `income`, `expense`.
  - for `income`, direction must be `in`; for `expense`, direction must be `out`.
  - security deposit deductions write `security_deposit_deduction` cash movements automatically.
  - supplier payments write `supplier_payment` cash movements automatically with `direction=out`.
  - supports `cashbox_id`, `balance_after`, `is_reversed`.

- Suppliers:
  - supplier payload includes `code`, `opening_balance`, `current_balance`, `total_purchase_orders`, `total_paid`, `total_remaining`.
  - list supports search by contact/name fields and status filter.
  - export endpoint available.

- Purchase Orders:
  - purchase order number is backend-generated.
  - totals/status are backend-calculated from items/payments.
  - status shifts automatically when supplier payments are added.
  - UI fields supported: `branch_id`, `category_id`, `subcategory_id`, `type`
  - return endpoint available: `POST /api/tenant/purchase-orders/{purchaseOrder}/return`
  - export endpoint available.

## 12) Deferred UI Contract Items (Round 1)

- `address_id` filter for customers: deferred because no dedicated address module/table exists yet.
- `inventory_id` dress filter: deferred because current backend inventory model is movement-based, not inventory-entity based.
- `employee_id` filters (invoices/payments): deferred because employee module is not implemented in current scope.

## 11) Frontend Safety Recommendations

- Never trust client-side total calculations alone.
- Always display backend-returned totals/status.
- On 401, clear token and redirect to login.
- On 403, show permission message.
- On 400/404 tenant errors, prompt workspace correction.
