# Tenant Isolation (Backend)

## Login (email + password only)

`POST /api/tenant/login`

```json
{ "email": "...", "password": "..." }
```

No `workspace` field. No tenant slug in the body.

The API resolves the tenant from the central `tenant_user_directory` table (one email → one tenant), connects that tenant database, then authenticates the user.

## Authenticated requests

After login, the client must send:

- `Authorization: Bearer {token}`
- `X-Tenant: {tenant_slug}` from the login response (`data.tenant.slug`)

Token `tenant_id` must match the `X-Tenant` header or the API returns **403**.

## Avatar storage

`storage/app/public/tenants/{tenant_id}/users/{user_id}/avatar/{uuid}.{ext}`

Upload: `POST /api/tenant/settings/profile/avatar`
