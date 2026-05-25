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
- `tenant:health` command
- Initial central and tenant migrations
- Central and tenant seeders for bootstrap defaults

## API structure

- `routes/api/platform.php`
  - `/api/platform/health`
  - `/api/platform/login`
  - `/api/platform/logout`
  - `/api/platform/me`

- `routes/api/tenant.php`
  - `/api/tenant/health`
  - `/api/tenant/login`
  - `/api/tenant/logout`
  - `/api/tenant/me`

## Documentation

- `docs/architecture.md`
- `docs/local-setup.md`
- `docs/tenant-flow.md`
