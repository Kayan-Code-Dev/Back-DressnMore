# Backend Gap Analysis - UI Contract Round 1

## Scope

This document tracks backend gaps identified against the design-first frontend contract for **already implemented modules** only.

Out of scope (intentionally not implemented in this phase):

- full reports module
- full accounting module
- payroll / HR module
- unrelated new domains

## Implemented in this round

### Part A - Customers

- Added customer UI fields:
  - `date_of_birth`, `phone2`, `city_id`, `source`
- Added customer filters:
  - `id`, `source`, `date_of_birth_from`, `date_of_birth_to`
- Added export endpoint:
  - `GET /api/tenant/customers/export`

### Part B - Branches

- Added full tenant branches module CRUD:
  - `GET/POST /api/tenant/branches`
  - `GET/PUT/DELETE /api/tenant/branches/{branch}`
- Added branch export:
  - `GET /api/tenant/branches/export`
- Added UI fields:
  - `branch_code`, VAT fields, currency fields, address details, notes, inventory/image/status fields

### Part C - Dresses

- Added dress UI fields:
  - `entity_type`, `entity_id`, `breast_size`, `waist_size`, `sleeve_size`, `measurements`,
    `delivery_date`, `days_of_rent`, `occasion_datetime`, `visit_datetime`
- Added dress endpoints:
  - `GET /api/tenant/dresses/{dress}/order-history`
  - `GET /api/tenant/dresses/available-for-date`
  - `GET /api/tenant/dresses/{dress}/unavailable-days`
  - `GET /api/tenant/dresses/export`
- Expanded dress filtering in listing API.

### Part D - Invoice actions

- Added invoice cancel endpoint:
  - `POST /api/tenant/invoices/{invoice}/cancel`
- Added invoice export endpoint:
  - `GET /api/tenant/invoices/export`
- Added invoice UI fields:
  - `branch_id`, `visit_datetime`, `occasion_datetime`, `days_of_rent`,
    `discount_type`, `discount_value`, `order_notes`
- Added support for `client_id` alias to `customer_id`.

### Part E - Standalone payments

- Added standalone payments foundation endpoints:
  - `GET /api/tenant/payments`
  - `GET /api/tenant/payments/{payment}`
  - `POST /api/tenant/payments/{payment}/pay`
  - `POST /api/tenant/payments/{payment}/cancel`
  - `GET /api/tenant/payments/export`
- Reused existing `InvoicePayment` model/service logic.
- Added payment status/type fields and filters.

### Part F - Expense workflow

- Added expense workflow fields:
  - `branch_id`, `cashbox_id`, `vendor`, `reference_number`, `status`,
    `approved_by`, `paid_at`, `cancelled_at`, `transaction_id`
- Added workflow endpoints:
  - `POST /api/tenant/expenses/{expense}/approve`
  - `POST /api/tenant/expenses/{expense}/cancel`
  - `POST /api/tenant/expenses/{expense}/pay`
  - `GET /api/tenant/expenses/summary`
  - `GET /api/tenant/expenses/export`

### Part G - Cashboxes

- Added minimal cashboxes module:
  - `GET/POST /api/tenant/cashboxes`
  - `GET/PUT/DELETE /api/tenant/cashboxes/{cashbox}`
  - `GET /api/tenant/cashboxes/{cashbox}/transactions`
  - `POST /api/tenant/cashboxes/{cashbox}/recalculate`
  - `GET /api/tenant/cashboxes/export`
  - `GET /api/tenant/cashboxes/daily-summary`
- Extended `cash_movements` with:
  - `cashbox_id`, `balance_after`, `is_reversed`

### Part H - Suppliers / Purchase Orders UI gaps

- Added supplier `code` field (nullable unique).
- Added supplier export:
  - `GET /api/tenant/suppliers/export`
- Added purchase order fields:
  - `branch_id`, `category_id`, `subcategory_id`, `type`,
    `is_returned`, `returned_at`, `return_notes`
- Added purchase order UI endpoints:
  - `GET /api/tenant/purchase-orders/export`
  - `POST /api/tenant/purchase-orders/{purchaseOrder}/return`

### Part I - Lookups expansion

Added/expanded lookup keys:

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

## Deferred in this round

- `address_id` customer filter: no dedicated address table/module exists in current architecture.
- `inventory_id` dress filter: current inventory design is movement-based and does not expose inventory entities.
- `employee_id` filters in invoices/payments: employee module is outside current completed-module scope.
- complex purchase-order return stock reversal: intentionally deferred to later inventory/accounting phase.

## Testing coverage added

New/updated tests included:

- `TenantCustomerUiContractTest`
- `TenantBranchTest`
- `TenantDressAvailabilityTest`
- `TenantInvoiceActionTest`
- `TenantPaymentStandaloneTest`
- `TenantExpenseWorkflowTest`
- `TenantCashboxTest`
- `TenantSupplierUiContractTest`
- `TenantLookupTest` (expanded assertions)
