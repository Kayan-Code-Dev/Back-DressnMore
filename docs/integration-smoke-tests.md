# Integration Smoke Tests (Backend ↔ Frontend)

This checklist is for quick staging/local integration validation against the current API contract.

## Placeholders

- `{{BASE_URL}}` example: `http://localhost:8000`
- `{{TENANT_SLUG}}` example: `demo`
- `{{TOKEN}}` from tenant login response
- Optional IDs (replace with real values): `{{CUSTOMER_ID}}`, `{{BRANCH_ID}}`, `{{CATEGORY_ID}}`, `{{SUBCATEGORY_ID}}`, `{{DRESS_ID}}`, `{{INVOICE_ID}}`, `{{EXPENSE_ID}}`, `{{SUPPLIER_ID}}`, `{{PURCHASE_ORDER_ID}}`

## 0) Demo data prerequisite

```bash
php artisan demo:tenant-seed {{TENANT_SLUG}}
```

Notes:
- Command is designed to be safe for reruns by upserting deterministic demo keys.
- Existing tenant data is not deleted.

---

## 1) Tenant login

```bash
curl -X POST "{{BASE_URL}}/api/tenant/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "workspace": "{{TENANT_SLUG}}",
    "email": "demo.owner+{{TENANT_SLUG}}@dressnmore.test",
    "password": "password"
  }'
```

---

## 2) Lookups

```bash
curl "{{BASE_URL}}/api/tenant/lookups" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 3) Customers list/create

### List
```bash
curl "{{BASE_URL}}/api/tenant/customers?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Create
```bash
curl -X POST "{{BASE_URL}}/api/tenant/customers" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "name": "Smoke Customer",
    "phone": "01000011122",
    "source": "walk_in",
    "status": "active",
    "notes": "Created by smoke test"
  }'
```

---

## 4) Dress categories/subcategories

### Categories list
```bash
curl "{{BASE_URL}}/api/tenant/dress-categories?only_parents=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Subcategories list for parent
```bash
curl "{{BASE_URL}}/api/tenant/dress-categories?parent_id={{CATEGORY_ID}}" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 5) Branches

```bash
curl "{{BASE_URL}}/api/tenant/branches?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 6) Dresses list + available-for-date

### List
```bash
curl "{{BASE_URL}}/api/tenant/dresses?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Available for date
```bash
curl "{{BASE_URL}}/api/tenant/dresses/available-for-date?date=2026-06-10&branch_id={{BRANCH_ID}}" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 7) Invoices list/create/cancel

### List
```bash
curl "{{BASE_URL}}/api/tenant/invoices?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Create (sell invoice)
```bash
curl -X POST "{{BASE_URL}}/api/tenant/invoices" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "customer_id": {{CUSTOMER_ID}},
    "branch_id": {{BRANCH_ID}},
    "type": "sell",
    "status": "confirmed",
    "items": [
      {
        "dress_id": {{DRESS_ID}},
        "quantity": 1,
        "unit_price": 2000
      }
    ]
  }'
```

### Cancel
```bash
curl -X POST "{{BASE_URL}}/api/tenant/invoices/{{INVOICE_ID}}/cancel" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 8) Payments list

```bash
curl "{{BASE_URL}}/api/tenant/payments?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 9) Expenses list/create/approve/pay

### List
```bash
curl "{{BASE_URL}}/api/tenant/expenses?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Create (pending)
```bash
curl -X POST "{{BASE_URL}}/api/tenant/expenses" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "expense_category_id": 1,
    "branch_id": {{BRANCH_ID}},
    "amount": 150,
    "status": "pending",
    "expense_date": "2026-06-01",
    "method": "cash",
    "description": "Smoke expense"
  }'
```

### Approve
```bash
curl -X POST "{{BASE_URL}}/api/tenant/expenses/{{EXPENSE_ID}}/approve" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Pay
```bash
curl -X POST "{{BASE_URL}}/api/tenant/expenses/{{EXPENSE_ID}}/pay" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "method": "cash",
    "paid_at": "2026-06-01 12:00:00",
    "notes": "Smoke payment"
  }'
```

---

## 10) Cashboxes list

```bash
curl "{{BASE_URL}}/api/tenant/cashboxes?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

---

## 11) Suppliers list/create

### List
```bash
curl "{{BASE_URL}}/api/tenant/suppliers?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Create
```bash
curl -X POST "{{BASE_URL}}/api/tenant/suppliers" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "code": "SMOKE-SUP-001",
    "name": "Smoke Supplier",
    "phone": "01044445555",
    "status": "active"
  }'
```

---

## 12) Purchase orders list/create

### List
```bash
curl "{{BASE_URL}}/api/tenant/purchase-orders?page=1&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}"
```

### Create
```bash
curl -X POST "{{BASE_URL}}/api/tenant/purchase-orders" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "supplier_id": {{SUPPLIER_ID}},
    "branch_id": {{BRANCH_ID}},
    "status": "confirmed",
    "items": [
      {
        "item_name": "Smoke Fabric",
        "quantity": 5,
        "unit_price": 100
      }
    ]
  }'
```

---

## 13) Supplier payment create

```bash
curl -X POST "{{BASE_URL}}/api/tenant/suppliers/{{SUPPLIER_ID}}/payments" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -d '{
    "purchase_order_id": {{PURCHASE_ORDER_ID}},
    "amount": 250,
    "method": "cash",
    "reference": "SMOKE-SUP-PAY-001"
  }'
```

---

## Optional: export smoke checks

```bash
curl -L "{{BASE_URL}}/api/tenant/customers/export" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -o customers.csv
```

```bash
curl -L "{{BASE_URL}}/api/tenant/invoices/export" \
  -H "Authorization: Bearer {{TOKEN}}" \
  -H "X-Tenant: {{TENANT_SLUG}}" \
  -o invoices.csv
```
