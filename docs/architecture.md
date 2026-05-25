# DressnMore SaaS Backend Architecture

## Core principles

- One Laravel backend codebase for all tenants.
- Central database stores platform-level data.
- Tenant database stores tenant-level operational data.
- Tenant resolution must happen before tenant authentication.
- Tenant database switching must happen before tenant user queries.
- Platform authentication is completely separate from tenant authentication.

## Database split

### Central (`central` connection)

- `super_admins`
- `tenants`
- `plans`
- `plan_features`
- `subscriptions`
- `payments`
- `tenant_domains`
- `tenant_provisioning_logs`

### Tenant (`tenant` connection)

- `users`
- `roles`
- `permissions`
- `role_user`
- `permission_role`
- `settings`

## Middleware execution order

Tenant routes must execute in this order:

1. `identify.tenant`
2. `check.tenant.subscription`
3. `set.tenant.database`
4. `auth:sanctum` (for protected tenant routes)

## Auth flow summary

### Platform auth

- Uses `SuperAdmin` model on `central` connection.
- Endpoints under `/api/platform/*`.

### Tenant auth

- Requires tenant workspace (`workspace`, `X-Tenant`, or `tenant` query).
- Resolves tenant in central DB.
- Validates tenant/subscription state.
- Switches `tenant` DB connection dynamically.
- Authenticates tenant user from tenant DB.
