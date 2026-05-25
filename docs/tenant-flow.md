# Tenant Request Flow

## Tenant identification

`IdentifyTenant` resolves tenant in this order:

1. Request input `workspace`
2. Header `X-Tenant`
3. Query parameter `tenant`

If missing: `400 {"message":"Tenant workspace is required"}`

If not found: `404 {"message":"Tenant not found"}`

## Subscription guard

`CheckTenantSubscription` checks central tenant record only:

- tenant status is `active`
- `subscription_ends_at` is null or not expired

If not valid, request is blocked with `403`.

## Tenant database switch

`SetTenantDatabase`:

- sets `database.connections.tenant.database` dynamically from tenant record
- runs `DB::purge('tenant')`
- runs `DB::reconnect('tenant')`
- validates with `SELECT 1`

If connection fails:

- logs internal error
- returns `500 {"message":"Tenant database connection failed"}`

## Tenant login

`POST /api/tenant/login` runs middleware in required order:

1. identify tenant
2. check subscription
3. set tenant database

Then auth service validates tenant user credentials from tenant DB and returns:

- token
- user
- tenant
- permissions
- plan

## Tenant provisioning concept (high-level)

- Create central tenant record with `provisioning` status.
- Create tenant database.
- Run tenant migrations and seeders.
- Create owner user and defaults.
- Mark tenant `active` on success.
