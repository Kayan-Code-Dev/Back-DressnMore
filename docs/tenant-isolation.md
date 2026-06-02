# Tenant Data Isolation

DressnMore is a **multi-tenant SaaS** backend. Each tenant has a **physically separate database**. There is no shared business-data table across tenants.

## Isolation model

| Layer | Mechanism |
|-------|-----------|
| **Central DB** | Tenants registry, plans, subscriptions, user directory, Sanctum tokens |
| **Tenant DB** | One database per tenant (`tenants.database_name`) |
| **Request routing** | `workspace` body field, `X-Tenant` header, or `?tenant=` query |
| **Connection switch** | `SetTenantDatabase` purges/reconnects `tenant` connection per request |
| **Auth binding** | Sanctum tokens for tenant users store `tenant_id` on `personal_access_tokens` |
| **Email directory** | `tenant_user_directory` maps each email to exactly one tenant |

## Middleware order (tenant API)

1. `identify.tenant` — resolve slug → `TenantContext`
2. `check.tenant.subscription` — central status + expiry
3. `set.tenant.database` — connect tenant DB
4. `auth:sanctum` — bearer token
5. `ensure.tenant.token` — token `tenant_id` must match resolved tenant

## Login rules

- Workspace is **required** (body or `X-Tenant` header).
- Email must exist in `tenant_user_directory` for **that** workspace.
- Credentials are validated only inside the tenant database.
- Issued tokens are stamped with `tenant_id`.

## What this prevents

- Using a token from tenant A with `X-Tenant: tenant-b` → **403**
- Logging into tenant B with an email registered to tenant A → **422**
- Cross-tenant data reads via ID collision (separate DBs) → **404** if token binding passed

## Provisioning

`TenantProvisioningService` creates:

1. Central tenant row + `database_name`
2. Physical database (`CREATE DATABASE` / SQLite file)
3. Tenant migrations + seeders
4. Owner user in tenant DB
5. Directory entry via `TenantUserDirectoryService::register()`

## Tests

- `tests/Feature/TenantDataIsolationTest.php` — cross-tenant auth + DB separation
- `tests/Feature/TenantAuthLoginTest.php` — workspace-bound login
- `tests/Feature/TenantJournalEntryTest.php` — resource isolation with mismatched workspace

## Operational notes

- After deploying token binding, users with old tokens (no `tenant_id`) must **log in again**.
- Run central migration: `add_tenant_id_to_personal_access_tokens`.
- Future queues/cache/storage must include tenant slug or id in keys/paths.
