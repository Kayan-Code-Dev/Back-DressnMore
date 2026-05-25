# Old Backend -> New Clean Architecture Migration/Refactor Plan

> Old reference repository: `erp-bahaa-eldin`  
> New target repository: `Back-DressnMore`  
> Policy: reuse **business logic only**, never copy old tenancy/auth/deployment mess.

## Legend

- **Keep**: Can be moved with minimal changes.
- **Refactor**: Reuse domain logic, rewrite structure/contracts.
- **Remove**: Do not migrate.

## File-by-file migration plan

| Old file path | Purpose in old repo | Decision | New target path | Notes |
|---|---|---|---|---|
| `app/Providers/TenancyServiceProvider.php` | Stancl tenancy bootstrapping with domain/subdomain assumptions | **Remove** | N/A | Replaced by explicit middleware chain: `IdentifyTenant -> CheckTenantSubscription -> SetTenantDatabase`. |
| `app/Http/Middleware/InitializeTenancyByTenantHostname.php` | Hostname-based tenant resolution | **Remove** | N/A | New system resolves tenant by workspace slug from body/header/query (no subdomain dependency). |
| `app/Http/Middleware/CheckTenantActive.php` | Mixed tenant active/trial checks | **Refactor** | `app/Http/Middleware/CheckTenantSubscription.php` | Keep validation intent only; use central `subscriptions + plans` and explicit error messages. |
| `app/Http/Controllers/AuthController.php` | Tenant auth in mixed context | **Remove** | N/A | Replaced by `app/Http/Controllers/Tenant/AuthController.php` with DB already switched before auth. |
| `app/Http/Controllers/Central/CentralAuthController.php` | Mixed admin + tenant login entrypoint | **Refactor** | `app/Http/Controllers/Platform/AuthController.php` | Keep platform-only auth logic; remove tenant fallback login from central endpoint. |
| `app/Services/Central/TenantPortalLoginService.php` | Central-host tenant login scanning across tenants | **Remove** | N/A | Violates clean flow/security model; tenant login must resolve tenant first then authenticate inside tenant DB. |
| `app/Http/Controllers/Central/TenantController.php` | Tenant CRUD + provisioning + domain ops | **Refactor** | `app/Http/Controllers/Platform/TenantController.php` + `app/Services/Tenant/TenantProvisioningService.php` | Keep provisioning sequence concept but rebuild with explicit status transitions and structured error logging. |
| `app/Services/Central/TenantProvisioner.php` | Legacy tenant creation/domain endpoint helper | **Refactor** | `app/Services/Tenant/TenantProvisioningService.php` | Keep DB-name generation/provisioning intent; remove host/domain coupling and old caching secrets approach. |
| `app/Http/Controllers/OrderController.php` | Main order flows (rent/sale) | **Refactor** | `app/Http/Controllers/Tenant/InvoiceController.php` + future `app/Services/Tenant/InvoiceService.php` | Extract financial/order business rules only; drop old controller bloat and mixed concerns. |
| `app/Services/OrderService.php` | Order pricing/availability/business rules | **Refactor** | future `app/Services/Tenant/InvoiceService.php`, `DressAvailabilityService.php` | Good domain logic candidate, but split into focused services and modern DTO/Request layers. |
| `app/Http/Controllers/ClothController.php` | Dress/cloth inventory workflows | **Refactor** | `app/Http/Controllers/Tenant/DressController.php` + future inventory services | Keep inventory and availability business behavior, rewrite API contracts and remove giant controller anti-pattern. |
| `app/Http/Controllers/ClientController.php` | Customer CRUD + measurements | **Refactor** | `app/Http/Controllers/Tenant/CustomerController.php` + future requests/resources | Reuse validation/business rules, not raw controller structure or response shape noise. |
| `app/Http/Controllers/SupplierController.php` | Supplier CRUD | **Refactor** | `app/Http/Controllers/Tenant/SupplierController.php` | Logic is reusable but rewritten as thin controller + service pipeline. |
| `app/Services/SupplierService.php` | Supplier listing/stats service | **Refactor** | future `app/Services/Tenant/SupplierService.php` | Reuse query intent; adjust to clean tenant-only models and resources. |
| `app/Http/Controllers/EmployeeController.php` | HR employee module | **Refactor** | `app/Http/Controllers/Tenant/EmployeeController.php` + future service | Keep business rules only; remove permission coupling from old controller level. |
| `app/Http/Controllers/ReportController.php` | Reporting endpoints | **Refactor** | future `app/Http/Controllers/Tenant/ReportController.php` + query services | Rebuild as dedicated read services with plan-feature gating middleware. |
| `app/Services/TransactionService.php` | Accounting transaction logic | **Refactor** | future `app/Services/Tenant/Accounting/TransactionService.php` | Reuse concepts; redesign boundaries and avoid coupling with unrelated modules. |
| `database/tenant_migrations/*` | Tenant business schema source | **Refactor** | `database/migrations/tenant/*` | Keep entity intent only; rebuild clean tables and naming for new architecture. |
| `database/migrations/*` (central plan/subscription parts) | Central billing/tenant schema | **Refactor** | `database/migrations/*` (central connection migrations) | Keep platform entities (tenants/plans/subscriptions/payments), normalize fields and status model. |
| `routes/tenant-api.php` | Massive tenant route map | **Remove** | N/A | Replaced by modular route files: `routes/api/platform.php` and `routes/api/tenant.php`. |
| `deploy/*`, `nginx*.conf`, `NGINX_*.md`, `REVERB_*` | Deployment/server config from old stack | **Remove** | N/A | Explicitly excluded per requirements; new deployment docs will be rebuilt later. |
| `.env`-style and host-specific assumptions | Environment-specific hardcoded config | **Remove** | N/A | No tenant-specific `.env`; only central static env + runtime tenant DB switching. |
| Duplicated/debug/temp files (`error_log`, samples, ad-hoc scripts) | Non-production artifacts | **Remove** | N/A | Not part of clean backend baseline. |

## Migration order for business modules (after foundation stabilizes)

1. Customers (`ClientController` + related models/requests)
2. Dresses (`ClothController` + inventory behaviors)
3. Invoices/Orders (`OrderController` + `OrderService`)
4. Suppliers (`SupplierController` + `SupplierService`)
5. Employees (core HR essentials only)
6. Reports (read-only analytics layer)
7. Accounting (`TransactionService` and finance-related modules)

## Guardrails during migration

- Never move old auth/tenancy code as-is.
- Every migrated module must:
  - use `App\Models\Tenant\*`,
  - rely on tenant DB already switched by middleware,
  - return unified API JSON responses,
  - use Form Requests and Resources,
  - be covered by tests before marking complete.
