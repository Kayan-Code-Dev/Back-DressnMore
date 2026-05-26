# Staging Environment Checklist (Backend Integration)

This checklist focuses on tenant API integration readiness for staging/production.

## 1) Application environment

- [ ] `APP_ENV=staging` (or `production` on prod)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://<backend-domain>`
- [ ] `APP_KEY` set and valid

## 2) Frontend integration env (recommended)

Frontend app env:
- [ ] `VITE_API_BASE_URL=https://<backend-domain>/api`

Backend env recommendations:
- [ ] `SANCTUM_STATEFUL_DOMAINS=<frontend-domain-list>` (if cookie/session auth is used)
- [ ] `SESSION_DOMAIN=<shared-parent-domain-or-null>`
- [ ] CORS allowed origins list includes frontend domains for staging/prod

Example values (adjust to your domains):
- `SANCTUM_STATEFUL_DOMAINS=staging-app.example.com,app.example.com`
- `SESSION_DOMAIN=.example.com`
- `CORS_ALLOWED_ORIGINS=https://staging-app.example.com,https://app.example.com`

> Do not hardcode production domains in code; keep them environment-driven.

## 3) CORS/Sanctum config review (current repo state)

- `config/cors.php`: **not present in repository currently**
  - Action: publish/add CORS config before staging if frontend and backend are on different origins.
  - Recommended behavior:
    - explicit allowed origins from env
    - allow `Authorization`, `Content-Type`, `Accept`, `X-Tenant`
    - support `OPTIONS` preflight for `/api/*`
- `config/sanctum.php`: present with custom token model only.
  - Current tenant API flow uses bearer tokens and works without stateful cookie setup.
  - If SPA cookie-based auth is planned later, configure `stateful` domains and session/csrf settings.

## 4) Database and tenant provisioning

Central database:
- [ ] `DB_CONNECTION=central`
- [ ] `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` set for central DB
- [ ] central DB credentials can create/manage tenant DBs when provisioning is required

Tenant database:
- [ ] tenant DB user has required DDL/DML privileges for tenant migrations/seeders
- [ ] each tenant row has valid `database_name`
- [ ] `php artisan tenant:health <tenant-slug>` passes

## 5) Queue, scheduler, storage

- [ ] `QUEUE_CONNECTION` configured for staging workload
- [ ] Scheduler running (`php artisan schedule:run` via cron/supervisor)
- [ ] `php artisan storage:link` executed if public file serving is needed
- [ ] log channel/rotation suitable for staging load

## 6) Security and API headers

- [ ] reverse proxy forwards `Authorization` header
- [ ] frontend sends:
  - `Accept: application/json`
  - `Authorization: Bearer <token>`
  - `X-Tenant: <workspace>`
- [ ] TLS enabled end-to-end in staging/prod

## 7) Quick smoke verification after deploy

- [ ] `GET /api/platform/health` returns success
- [ ] `GET /api/tenant/health` returns success with valid `X-Tenant`
- [ ] login + lookups + one CRUD flow succeeds
- [ ] one export endpoint returns downloadable CSV
