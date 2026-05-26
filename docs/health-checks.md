# Health Checks

This document describes the current platform/tenant health endpoints and expected responses.

## 1) Platform health

### Endpoint

`GET /api/platform/health`

### Auth

No authentication required.

### Expected success response (200)

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "app_name": "DressnMore",
    "environment": "staging",
    "central_database_connection": true,
    "timestamp": "2026-05-26T23:30:00+00:00"
  },
  "meta": {}
}
```

### Notes

- `central_database_connection` reflects runtime DB probe (`SELECT 1`) on central connection.

---

## 2) Tenant health

### Endpoint

`GET /api/tenant/health`

### Required headers

- `Accept: application/json`
- `X-Tenant: <workspace>`

### Auth

No bearer token required for this endpoint (tenant is identified by workspace).

### Expected success response (200)

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "tenant_name": "Demo Tenant",
    "tenant_slug": "demo",
    "tenant_database_name": "dressnmore_tenant_demo",
    "tenant_database_connection": true,
    "timestamp": "2026-05-26T23:30:00+00:00"
  },
  "meta": {}
}
```

### Failure examples

- Missing workspace:
  - `400`, message: `Tenant workspace is required`
- Invalid workspace:
  - `404`, message: `Tenant not found`

### Notes

- Endpoint reports connection health only; it does not validate business-module readiness.

---

## 3) Operational recommendations

- Include both health endpoints in monitoring.
- Alert when:
  - platform health fails
  - tenant DB connection returns false for critical tenants
- Keep at least one synthetic smoke flow (login + lookups + simple list endpoint) in staging checks.
