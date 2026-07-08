#!/usr/bin/env bash
# Tenant API readiness smoke test - exercises key endpoints as a logged-in tenant.
set -u
BASE="http://127.0.0.1:8000/api"
TENANT="test"
EMAIL="admin@test.local"
PASS="password123"

pass=0; fail=0
declare -a FAILED

login() {
  TOKEN=$(curl -s -X POST "$BASE/tenant/login" -H "X-Tenant: $TENANT" -H "Accept: application/json" -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['token'])" 2>/dev/null)
  if [ -z "${TOKEN:-}" ]; then echo "LOGIN FAILED"; exit 1; fi
  echo "Token acquired: ${TOKEN:0:12}..."
}

# check METHOD PATH EXPECTED_CODE LABEL [DATA]
check() {
  local method="$1" path="$2" expected="$3" label="$4" data="${5:-}"
  local args=(-s -o /tmp/resp.json -w "%{http_code}" -X "$method" "$BASE$path" \
    -H "X-Tenant: $TENANT" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN")
  if [ -n "$data" ]; then args+=(-H "Content-Type: application/json" -d "$data"); fi
  local code
  code=$(curl "${args[@]}")
  if [ "$code" = "$expected" ]; then
    printf "  [PASS] %-6s %-45s -> %s\n" "$method" "$path" "$code"
    pass=$((pass+1))
  else
    printf "  [FAIL] %-6s %-45s -> %s (expected %s)\n" "$method" "$path" "$code" "$expected"
    echo "         body: $(head -c 300 /tmp/resp.json)"
    fail=$((fail+1)); FAILED+=("$method $path -> $code (exp $expected)")
  fi
}

login
echo "=== AUTH / IDENTITY ==="
check GET  /tenant/me 200 "me"
check GET  /tenant/lookups 200 "lookups"
check GET  /tenant/subscription/overview 200 "subscription overview"

echo "=== DASHBOARD / REPORTS ==="
check GET  /tenant/dashboard/overview 200 "dashboard overview"
check GET  /tenant/reports/catalog 200 "reports catalog"
check GET  /tenant/reports/overview 200 "reports overview"

echo "=== CUSTOMERS ==="
check GET  /tenant/customers 200 "customers list"
check GET  /tenant/customers/stats 200 "customers stats"
check POST /tenant/customers 201 "create customer" '{"name":"عميل اختبار","phone":"0599123456"}'

echo "=== BRANCHES ==="
check GET  /tenant/branches 200 "branches list"

echo "=== DRESSES / CATEGORIES ==="
check GET  /tenant/dress-categories 200 "dress categories"
check GET  /tenant/dresses 200 "dresses list"

echo "=== INVOICES / SALES / RENTAL ==="
check GET  /tenant/invoices 200 "invoices list"
check GET  /tenant/sales/invoices 200 "sales invoices"
check GET  /tenant/sales/invoices/stats 200 "sales invoice stats"
check GET  /tenant/orders/rental 200 "rental orders"
check GET  /tenant/orders/rental/stats 200 "rental stats"

echo "=== DELIVERIES / RETURNS ==="
check GET  /tenant/deliveries 200 "deliveries"
check GET  /tenant/deliveries/stats 200 "deliveries stats"
check GET  /tenant/returns 200 "returns"
check GET  /tenant/returns/overdue 200 "overdue returns"

echo "=== TAILORING ==="
check GET  /tenant/tailoring/orders 200 "tailoring orders"
check GET  /tenant/tailoring/orders/stats 200 "tailoring stats"
check GET  /tenant/tailoring/workshop-board 200 "workshop board"

echo "=== FINANCE ==="
check GET  /tenant/expenses 200 "expenses"
check GET  /tenant/expense-categories 200 "expense categories"
check GET  /tenant/cashboxes 200 "cashboxes"
check GET  /tenant/cashboxes/daily-summary 200 "cashbox daily summary"
check GET  /tenant/cash-movements 200 "cash movements"
check GET  /tenant/payments 200 "payments"
check GET  /tenant/accounting/summary 200 "accounting summary"
check GET  /tenant/accounting/journal-entries 200 "journal entries"
check GET  /tenant/accounting/ledger 200 "accounting ledger"

echo "=== SUPPLIERS / PURCHASE ORDERS ==="
check GET  /tenant/suppliers 200 "suppliers"
check GET  /tenant/purchase-orders 200 "purchase orders"
check GET  /tenant/supplier-payments 200 "supplier payments"

echo "=== HR ==="
check GET  /tenant/hr/dashboard 200 "hr dashboard"
check GET  /tenant/hr/departments 200 "hr departments"
check GET  /tenant/hr/job-titles 200 "hr job titles"
check GET  /tenant/hr/employees 200 "hr employees"
check GET  /tenant/hr/shifts 200 "hr shifts"
check GET  /tenant/hr/attendance 200 "hr attendance"
check GET  /tenant/hr/leaves 200 "hr leaves"
check GET  /tenant/hr/payroll 200 "hr payroll"
check GET  /tenant/hr/settings 200 "hr settings"
check GET  /tenant/hr/documents 200 "hr documents"

echo "=== EMPLOYEES / WORKSHOPS / NOTIFICATIONS ==="
check GET  /tenant/employees 200 "employees"
check GET  /tenant/workshops 200 "workshops"
check GET  /tenant/factories 200 "factories"
check GET  /tenant/notifications 200 "notifications"

echo "=== SECURITY: no tenant header should fail ==="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/tenant/me" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN")
if [ "$code" = "400" ] || [ "$code" = "404" ]; then printf "  [PASS] missing X-Tenant rejected -> %s\n" "$code"; pass=$((pass+1)); else printf "  [FAIL] missing X-Tenant -> %s\n" "$code"; fail=$((fail+1)); FAILED+=("no-tenant -> $code"); fi

echo "=== SECURITY: bad token should 401 ==="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/tenant/me" -H "X-Tenant: $TENANT" -H "Accept: application/json" -H "Authorization: Bearer badtoken")
if [ "$code" = "401" ]; then printf "  [PASS] bad token rejected -> %s\n" "$code"; pass=$((pass+1)); else printf "  [FAIL] bad token -> %s\n" "$code"; fail=$((fail+1)); FAILED+=("bad-token -> $code"); fi

echo
echo "==================== SUMMARY ===================="
echo "PASS: $pass    FAIL: $fail"
if [ "$fail" -gt 0 ]; then printf '%s\n' "${FAILED[@]}"; fi
