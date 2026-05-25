# Back-DressnMore

Clean Laravel backend for DressnMore SaaS with strict multi-tenant architecture.

## Current foundation

- Laravel 13 project baseline
- Central and tenant database separation
- Workspace-based tenant resolution
- Tenant middleware pipeline:
  - `IdentifyTenant`
  - `CheckTenantSubscription`
  - `SetTenantDatabase`
- Separate platform and tenant auth controllers/services
- Provisioning service and `tenant:health` command
- Initial central and tenant migrations

## API structure

- `routes/api/platform.php`
  - `/api/platform/login`
  - `/api/platform/logout`
  - `/api/platform/me`
  - `/api/platform/tenants`
  - `/api/platform/plans`
  - `/api/platform/subscriptions`

- `routes/api/tenant.php`
  - `/api/tenant/login`
  - `/api/tenant/logout`
  - `/api/tenant/me`
  - `/api/tenant/dashboard`
  - tenant module resources (users, roles, branches, employees, customers, dresses, invoices, suppliers, settings)

## Migration planning document

See:

- `docs/migration-refactor-plan.md`

This file maps old repository files/modules to keep/refactor/remove decisions and new target paths.
