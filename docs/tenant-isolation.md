# Tenant Isolation (Backend)

## Tenant identification

Tenant context is resolved **before** authentication and database switching:

1. `X-Tenant` header (primary — used by the tenant frontend)
2. `?tenant=` query parameter
3. Subdomain of configured base domains (`TENANT_BASE_DOMAINS`, default `dressnmore.it.com,localhost`)

If missing: `400 {"message":"Tenant context is required"}`

**No `workspace` body field** is used or required anywhere in the tenant auth pipeline.

## Login

`POST /api/tenant/login`

Headers:

- `X-Tenant: {tenant_slug}`

Body:

```json
{ "email": "...", "password": "..." }
```

Flow:

1. Identify tenant from header/domain/query
2. Validate tenant is active (subscription middleware)
3. Switch to tenant database (`set.tenant.database`)
4. Verify email exists in central `tenant_user_directory` for that tenant
5. Authenticate user from **tenant DB** `users` table
6. Issue Sanctum token with `tenant_id` stored on `personal_access_tokens`

## Token binding

Protected routes run `ensure.tenant.token` **after** `auth:sanctum`.

A token issued for Tenant A returns **403** when used with `X-Tenant: tenant-b`:

`Token is not valid for this tenant`

## Avatar / profile images

New uploads: `storage/app/public/tenants/{tenant_id}/users/{user_id}/avatar/{uuid}.{ext}`

- Stored path saved in `users.avatar_path`
- `avatar_url` is generated tenant-aware; cross-tenant paths are not exposed
- Upload: `POST /api/tenant/settings/profile/avatar` (multipart `avatar`)

Legacy non-tenant paths remain readable if already stored; new writes always use tenant-scoped paths.

## Tests

- `tests/Feature/TenantDataIsolationTest.php`
- `tests/Feature/TenantAuthAndAvatarIsolationTest.php`
- `tests/Feature/TenantAuthLoginTest.php`
