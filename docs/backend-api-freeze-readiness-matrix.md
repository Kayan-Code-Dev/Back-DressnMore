# Backend API Freeze Readiness Matrix

Status values:
- `ready_for_frontend`
- `partial`
- `deferred`
- `blocked`

| Module | Endpoints ready | Permissions ready | Lookups ready | Tests ready | Export ready | Frontend dependency | Status | Notes |
|---|---|---|---|---|---|---|---|---|
| Auth | Yes (`/login`, `/logout`, `/me`, `/health`) | N/A (auth-only for tenant auth routes) | N/A | Yes | N/A | Login/session bootstrap | ready_for_frontend | Stable envelope and session shape |
| Lookups | Yes (`GET /lookups`) | Auth-only | Yes | Yes (`TenantLookupTest`) | N/A | Required by nearly all forms/filters | ready_for_frontend | Canonical lookup source |
| Dashboard | No tenant dashboard route | `dashboard.view` exists but route missing | Partial (`report_periods` available) | No dedicated dashboard API test | N/A | Dashboard overview cards/charts | deferred | API intentionally not implemented yet |
| Customers | Yes | Yes | Yes (`customer_statuses`, `customer_sources`) | Yes (`TenantCustomerTest`, `TenantCustomerUiContractTest`) | Yes | Customer module integration | ready_for_frontend | `address_id` filter deferred |
| Dress Categories | Yes | Yes | Yes (`category_statuses`) | Yes (`TenantDressCategoryTest`) | No | Categories/subcategories management | partial | Separate export not available |
| Subcategories | Yes (via dress-categories + `parent_id`) | Yes | Yes | Yes | No | Subcategory selector screens | partial | No dedicated `/subcategories` route |
| Branches | Yes | Yes | Yes (`branch_statuses`, `vat_types`) | Yes (`TenantBranchTest`) | Yes | Branch CRUD and filters | ready_for_frontend | Currency/city are scalar IDs |
| Dresses | Yes | Yes | Yes (`dress_statuses`) | Yes (`TenantDressTest`, `TenantDressAvailabilityTest`) | Yes | Dresses inventory/availability views | ready_for_frontend | `inventory_id` filter deferred |
| Inventory Movements | Yes (`/dresses/{dress}/inventory-movements`) | Yes (`inventory.view`) | Yes (`inventory_movement_types`) | Yes (`TenantDressTest`) | No | Movement history/timeline | partial | Standalone inventory-movements endpoint deferred |
| Invoices | Yes | Yes | Yes (`invoice_types`, `invoice_statuses`, deposit lookups) | Yes (`TenantInvoiceTest`, `TenantInvoiceActionTest`) | Yes | Core order flow (rent/sell/tailoring) | ready_for_frontend | `employee_id` filter deferred |
| Invoice Payments (nested) | Yes (`/invoices/{invoice}/payments`) | Yes | Yes (`payment_methods`) | Yes (`TenantInvoicePaymentTest`) | No | Invoice details payments tab | partial | Export handled by standalone payments endpoint |
| Standalone Payments | Yes (`/payments*`) | Yes | Yes (`payment_statuses`, `payment_types`, `payment_methods`) | Yes (`TenantPaymentStandaloneTest`) | Yes | Payments center module | ready_for_frontend | Built on InvoicePayment foundation |
| Deliveries | Yes (`deliver`, `delivery-records`) | Yes | Yes (`delivery_record_types`) | Yes (`TenantInvoiceDeliveryTest`) | No | Delivery workflow screens | partial | No dedicated export endpoint |
| Returns | Yes (`POST /invoices/{invoice}/return`) | Yes | Yes (`dress_status_after_return`) | Yes (`TenantInvoiceDeliveryTest`) | N/A | Return workflow action | ready_for_frontend | Rent-only return logic |
| Security Deposits | Yes (`deductions`, `transactions`) | Yes | Yes (`security_deposit_statuses`, transaction types) | Yes (`TenantSecurityDepositTest`) | No | Security deposit workflow | partial | No export endpoint |
| Expenses | Yes (`CRUD + approve/cancel/pay + summary + export`) | Yes | Yes (`expense_statuses`, `payment_methods`) | Yes (`TenantExpenseTest`, `TenantExpenseWorkflowTest`) | Yes | Expense workflow UI | ready_for_frontend | Workflow state model stabilized |
| Expense Categories | Yes | Yes | Yes (`expense_category_statuses`) | Yes (`TenantExpenseCategoryTest`) | No | Expense category setup | partial | Export endpoint deferred |
| Cash Movements | Yes (`list`, `manual create`) | Yes | Yes (`cash_movement_types`, `cash_movement_directions`) | Yes (`TenantCashMovementTest`) | No | Cash ledger list and manual adjustments | partial | Export endpoint deferred |
| Cashboxes | Yes (`CRUD + transactions + recalculate + daily-summary + export`) | Yes | Yes (`cashbox_statuses`) | Yes (`TenantCashboxTest`) | Yes | Cashbox screens and balances | ready_for_frontend | Balance recalculation included |
| Suppliers | Yes (`CRUD + export`) | Yes | Yes (`supplier_statuses`) | Yes (`TenantSupplierTest`, `TenantSupplierUiContractTest`) | Yes | Supplier screens | ready_for_frontend | Includes supplier code field |
| Purchase Orders | Yes (`CRUD + return + export + payments list`) | Yes | Yes (`purchase_order_statuses`) | Yes (`TenantPurchaseOrderTest`, `TenantSupplierUiContractTest`) | Yes | Purchasing workflows | ready_for_frontend | Return kept intentionally simple |
| Supplier Payments | Yes (`supplier payments list/create`, `PO payments list`) | Yes | Yes (`payment_methods`) | Yes (`TenantSupplierPaymentTest`) | No | Supplier payment tabs/forms | partial | Export endpoint deferred |
| Settings/Profile | Profile yes (`GET /me`), settings API no | Partial (`settings.manage` seeded, no route) | N/A | Partial (`/me` covered in auth tests) | N/A | Profile/settings screens | deferred | Dedicated settings endpoints not implemented |
