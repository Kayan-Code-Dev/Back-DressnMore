# Frontend-Backend Integration Contract (API Freeze)

This document defines the frozen integration contract between frontend clients and the tenant backend APIs.

## 1) Base URL rule

- Platform root: `/api`
- Tenant base prefix: `/api/tenant`
- Frontend service modules must use tenant prefix for tenant-scoped APIs.

## 2) Required headers

### Tenant authenticated endpoints

```http
Authorization: Bearer <token>
X-Tenant: <workspace-slug>
Accept: application/json
Content-Type: application/json
```

### Tenant unauthenticated endpoints

- `POST /api/tenant/login`
- `GET /api/tenant/health`

Required:

```http
X-Tenant: <workspace-slug>
Accept: application/json
```

(`Content-Type` required for JSON body requests.)

## 3) Standard success response

```json
{
  "success": true,
  "message": "Success",
  "data": {},
  "meta": {}
}
```

Notes:
- `meta` is always present.
- For non-paginated responses `meta` is an empty object.

## 4) Standard error response

```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

Common status handling:
- `400`: tenant/workspace requirement errors
- `401`: unauthenticated
- `403`: forbidden (permission missing)
- `404`: not found / tenant not found
- `422`: validation errors

## 5) Validation error format

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Validation message"
    ]
  }
}
```

Frontend recommendation:
- map each field to first message for form-level display
- show `message` for global toast/banner context

## 6) Pagination format

Paginated list responses return:

```json
{
  "success": true,
  "message": "Success",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

## 7) Export / download handling

Current freeze behavior:
- Export endpoints return streamed CSV attachments.
- Response headers include:
  - `Content-Disposition: attachment; filename=<module>.csv`
  - `Content-Type: text/csv; charset=UTF-8`

Frontend contract:
- treat exports as file-download requests (not JSON parsing)
- preserve filters in export query string to match list state
- expected export endpoints:
  - customers, branches, dresses, invoices, payments, expenses, cashboxes, suppliers, purchase-orders

## 8) Auth/session response shape

### Tenant login (`POST /api/tenant/login`)

`data` shape:
- `token: string`
- `user: UserResource`
- `tenant: { id, name, slug }`
- `permissions: string[]`
- `plan: object|null`

### Tenant me (`GET /api/tenant/me`)

`data` shape:
- `user: UserResource`
- `tenant: { id, name, slug }`
- `permissions: string[]`
- `plan: object|null`

## 9) Permission model

- Route access is enforced by `tenant.permission:<permission_key>` middleware where configured.
- Tenant permission keys are seeded by `TenantRolePermissionSeeder`.
- UI contract:
  - frontend should gate screens/actions based on `permissions` returned from login/me
  - backend remains source of truth and enforces permission checks regardless of frontend visibility
- Full permission-to-screen map is in `docs/permissions-map.md`.

## 10) Lookup strategy

- Endpoint: `GET /api/tenant/lookups`
- Purpose: canonical source for select/dropdown option values and labels.
- Frontend integration strategy:
  - fetch once after auth/workspace initialization
  - cache in app state/query cache
  - never hardcode enum values in frontend when lookup group is available
- Full lookup contract is in `docs/lookups-contract.md`.

## 11) Contract stability and deferred scope

Freeze rules for frontend integration phase:
- no new business modules added in this phase
- endpoint behavior should remain stable unless critical contract inconsistency is discovered
- deferred/not-yet-exposed endpoints currently include:
  - dashboard API
  - tenant settings API
  - employee-based filtering endpoints
  - inventory entity endpoints beyond movement foundation
