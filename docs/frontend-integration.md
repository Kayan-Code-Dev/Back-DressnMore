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
src/services/dresses.service.ts
src/services/invoices.service.ts
src/services/expenses.service.ts
src/services/cash-movements.service.ts
src/services/lookups.service.ts

src/types/api.ts
src/types/customer.ts
src/types/dress.ts
src/types/invoice.ts
src/types/expense.ts
src/types/cash-movement.ts
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

- `dresses.service.ts`
  - categories CRUD
  - dresses CRUD
  - inventory movement list

- `invoices.service.ts`
  - invoices CRUD
  - invoice payments list/create

- `lookups.service.ts`
  - fetch and cache lookups

- `expenses.service.ts`
  - expense categories CRUD
  - expenses CRUD

- `cash-movements.service.ts`
  - list cash movements
  - create manual cash movement

## 9) Suggested Type Shapes

- `customer.ts`
  - `Customer`, `CustomerPayload`
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
- `cash-movement.ts`
  - `CashMovement`
- `lookups.ts`
  - `LookupOption { value: string; label: string }`
  - `LookupsResponse`

## 10) Integration Notes by Module

- Customers:
  - list endpoints return `data: Customer[]` + pagination in `meta`.

- Dress Categories:
  - parent/child hierarchy in one table.
  - filters: `parent_id`, `only_parents`, `only_children`.

- Dresses:
  - preserve separate fields:
    - `code`
    - `dress_category_id`
    - `dress_subcategory_id`
  - UI display name should use backend `display_name`.

- Invoices:
  - totals are backend-calculated; frontend should not override.
  - rent overlap validation can return 422 with `rent_period` error key.
  - adding invoice payment also writes an `invoice_payment` cash movement.

- Expense Categories:
  - supports search by `name` and status filter.

- Expenses:
  - create/update/delete keeps linked `expense` cash movement in sync.
  - filters supported: category, method, date range, search fields.

- Cash Movements:
  - manual entries support `manual_adjustment`, `income`, `expense`.
  - for `income`, direction must be `in`; for `expense`, direction must be `out`.
  - security deposit deductions write `security_deposit_deduction` cash movements automatically.

## 11) Frontend Safety Recommendations

- Never trust client-side total calculations alone.
- Always display backend-returned totals/status.
- On 401, clear token and redirect to login.
- On 403, show permission message.
- On 400/404 tenant errors, prompt workspace correction.
