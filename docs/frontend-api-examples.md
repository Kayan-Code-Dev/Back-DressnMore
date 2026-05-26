# Frontend API Examples

This document provides concrete request/response examples aligned with the frozen backend contract.

Placeholders:
- `{{BASE_URL}}`
- `{{TENANT_SLUG}}`
- `{{TOKEN}}`

---

## 1) Login

### Request

```http
POST {{BASE_URL}}/api/tenant/login
Accept: application/json
Content-Type: application/json
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "workspace": "{{TENANT_SLUG}}",
  "email": "demo.owner+{{TENANT_SLUG}}@dressnmore.test",
  "password": "password"
}
```

### Response (200)

```json
{
  "success": true,
  "message": "Tenant login successful",
  "data": {
    "token": "1|token-value",
    "user": {
      "id": 1,
      "name": "Demo Owner DEMO",
      "email": "demo.owner+demo@dressnmore.test",
      "phone": "01099990001",
      "status": "active"
    },
    "tenant": {
      "id": 1,
      "name": "Demo Tenant",
      "slug": "demo"
    },
    "permissions": [
      "customers.view",
      "invoices.view"
    ],
    "plan": null
  },
  "meta": {}
}
```

---

## 2) Paginated list (customers)

### Request

```http
GET {{BASE_URL}}/api/tenant/customers?page=1&per_page=10
Accept: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

### Response (200)

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Demo Customer One",
      "phone": "01000001001",
      "status": "active"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 3,
    "last_page": 1
  }
}
```

---

## 3) Validation error example (customer create)

### Request

```http
POST {{BASE_URL}}/api/tenant/customers
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "name": "",
  "status": "unknown_status"
}
```

### Response (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

---

## 4) Export download example

### Request

```http
GET {{BASE_URL}}/api/tenant/invoices/export?status=paid
Accept: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

### Expected response headers

```text
HTTP/1.1 200 OK
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename=invoices.csv
```

Response body is CSV content (not JSON).

---

## 5) Create customer

### Request

```http
POST {{BASE_URL}}/api/tenant/customers
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "name": "Frontend Customer",
  "phone": "01012345678",
  "source": "walk_in",
  "status": "active"
}
```

### Response (201)

```json
{
  "success": true,
  "message": "Customer created",
  "data": {
    "id": 25,
    "name": "Frontend Customer",
    "phone": "01012345678",
    "source": "walk_in",
    "status": "active"
  },
  "meta": {}
}
```

---

## 6) Create invoice (sell)

### Request

```http
POST {{BASE_URL}}/api/tenant/invoices
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "customer_id": 25,
  "branch_id": 1,
  "type": "sell",
  "status": "confirmed",
  "items": [
    {
      "dress_id": 2,
      "quantity": 1,
      "unit_price": 6400
    }
  ],
  "notes": "Created from frontend flow"
}
```

### Response (201)

```json
{
  "success": true,
  "message": "Invoice created",
  "data": {
    "id": 44,
    "invoice_number": "INV-20260526-0007",
    "type": "sell",
    "status": "confirmed",
    "subtotal": "6400.00",
    "discount": "0.00",
    "tax": "0.00",
    "total": "6400.00",
    "paid_amount": "0.00",
    "remaining_amount": "6400.00",
    "items": [
      {
        "dress_id": 2,
        "quantity": 1,
        "unit_price": "6400.00",
        "total": "6400.00"
      }
    ]
  },
  "meta": {}
}
```

---

## 7) Expense payment workflow example

### Step A: create expense (pending)

```http
POST {{BASE_URL}}/api/tenant/expenses
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "expense_category_id": 1,
  "branch_id": 1,
  "amount": 300,
  "status": "pending",
  "expense_date": "2026-06-01",
  "method": "cash",
  "description": "Packing materials"
}
```

### Step B: approve

```http
POST {{BASE_URL}}/api/tenant/expenses/88/approve
Accept: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

### Step C: pay

```http
POST {{BASE_URL}}/api/tenant/expenses/88/pay
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{TOKEN}}
X-Tenant: {{TENANT_SLUG}}
```

```json
{
  "cashbox_id": 1,
  "method": "cash",
  "paid_at": "2026-06-01 14:00:00",
  "notes": "Paid from frontend"
}
```

### Final response snippet

```json
{
  "success": true,
  "message": "Expense paid",
  "data": {
    "id": 88,
    "status": "paid",
    "paid_at": "2026-06-01T14:00:00.000000Z",
    "amount": "300.00"
  },
  "meta": {}
}
```
