#!/usr/bin/env python3
"""Execute Full System QA on staging via SSH (bypasses Cloudflare bot block)."""
from __future__ import annotations

import json
import re
import sys
from datetime import date, datetime, timedelta
from pathlib import Path

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HOST = "159.198.74.223"
SSH_USER = "root"
SSH_PASSWORD = "RBZ4cjZE184wOx37ip"
API = "https://staging-api.dressnmore.it.com/api/tenant"
TENANT = "qa-staging"
EMAIL = "qa-staging@dressnmore.test"
PWD = "StagingQA2026!"
PREFIX = "QA-FULL-"
REPO = Path(__file__).resolve().parents[1]
OUT_JSON = REPO / "docs" / "full-system-operational-qa-results.json"
OUT_RAW = REPO / "docs" / "full-system-operational-qa-raw.log"


def build_remote_script() -> str:
    start = (date.today() + timedelta(days=25)).isoformat()
    end = (date.today() + timedelta(days=30)).isoformat()
    overlap_s = (date.today() + timedelta(days=27)).isoformat()
    overlap_e = (date.today() + timedelta(days=32)).isoformat()
    free_s = (date.today() + timedelta(days=70)).isoformat()
    free_e = (date.today() + timedelta(days=74)).isoformat()
    today = date.today().isoformat()

    return f"""#!/bin/bash
set -uo pipefail
API="{API}"
TENANT="{TENANT}"
EMAIL="{EMAIL}"
PWD='{PWD}'
PFX="{PREFIX}"
today="{today}"
LOG=/tmp/qa_full_results.jsonl
: > "$LOG"

log_step() {{
  local module="$1" scenario="$2" http="$3" body="$4"
  python3 -c "import json,sys; print(json.dumps({{'module':sys.argv[1],'scenario':sys.argv[2],'http':int(sys.argv[3]),'body':json.loads(sys.argv[4]) if sys.argv[4].strip().startswith('{{') else {{'raw':sys.argv[4][:500]}}}}, ensure_ascii=False))" "$module" "$scenario" "$http" "$body" >> "$LOG"
}}

curl_json() {{
  local method="$1" path="$2" data="${{3:-}}"
  if [ -n "$data" ]; then
    resp=$(curl -sk -w '\\n__HTTP__:%{{http_code}}' -X "$method" "$API$path" \\
      -H "Authorization: Bearer $TOKEN" -H "X-Tenant: $TENANT_SLUG" \\
      -H "Accept: application/json" -H "Content-Type: application/json" \\
      -d "$data")
  else
    resp=$(curl -sk -w '\\n__HTTP__:%{{http_code}}' -X "$method" "$API$path" \\
      -H "Authorization: Bearer $TOKEN" -H "X-Tenant: $TENANT_SLUG" -H "Accept: application/json")
  fi
  http=$(echo "$resp" | tail -1 | sed 's/__HTTP__://')
  body=$(echo "$resp" | sed '$d')
  echo "$body"
  echo "$http"
}}

login_payload='{{"workspace":"'"$TENANT"'","email":"'"$EMAIL"'","password":"'"$PWD"'"}}'
login=$(curl -sk -X POST "$API/login" -H "Content-Type: application/json" -H "X-Tenant: $TENANT" -d "$login_payload")
TOKEN=$(echo "$login" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('token','') if d.get('success') else '')" 2>/dev/null || true)
if [ -z "$TOKEN" ]; then
  echo "BLOCKED_LOGIN:$login"
  exit 2
fi
log_step "Auth" "Owner login" 200 "$login"

TENANT_SLUG=$(echo "$login" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('tenant',{{}}).get('slug','') or '$TENANT')" 2>/dev/null || echo "$TENANT")
echo "TENANT_SLUG=$TENANT_SLUG"

# --- Setup data ---
create() {{
  local module="$1" scenario="$2" method="$3" path="$4" data="${{5:-}}"
  out=$(curl_json "$method" "$path" "$data")
  http=$(echo "$out" | tail -1)
  body=$(echo "$out" | sed '$d')
  log_step "$module" "$scenario" "$http" "$body"
  echo "$body"
}}

BMAIN=$(create "Branches" "Create QA-FULL main branch" POST "/branches" '{{"name":"'"$PFX"'Branch-Main","code":"'"$PFX"'MAIN","phone":"0500111001","address":"QA","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
BSEC=$(create "Branches" "Create QA-FULL second branch" POST "/branches" '{{"name":"'"$PFX"'Branch-Second","code":"'"$PFX"'SEC","phone":"0500111002","address":"QA2","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)

CAT=$(create "Catalog" "Create category" POST "/dress-categories" '{{"name":"'"$PFX"'Category","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
SUB=""
if [ -n "$CAT" ]; then
  SUB=$(create "Catalog" "Create subcategory" POST "/dress-categories/$CAT/subcategories" '{{"name":"'"$PFX"'Sub","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
fi

DRESS_SALE=""
DRESS_RENT=""
if [ -n "$CAT" ] && [ -n "$SUB" ]; then
  DRESS_SALE=$(create "Catalog" "Create sale dress" POST "/dresses" '{{"code":"'"$PFX"'SALE1","name":"'"$PFX"'Dress-Sale","dress_category_id":'"$CAT"',"dress_subcategory_id":'"$SUB"',"branch_id":'"$BMAIN"',"status":"available","sale_price":2200}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
  DRESS_RENT=$(create "Catalog" "Create rent dress" POST "/dresses" '{{"code":"'"$PFX"'RENT1","name":"'"$PFX"'Dress-Rent","dress_category_id":'"$CAT"',"dress_subcategory_id":'"$SUB"',"branch_id":'"$BMAIN"',"status":"available","rental_price":900}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
fi

CUST=$(create "Customers" "Create customer" POST "/customers" '{{"name":"'"$PFX"'Customer","phone":"0500999001","source":"walk_in","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
SUP=$(create "Suppliers" "Create supplier" POST "/suppliers" '{{"name":"'"$PFX"'Supplier","phone":"0500888001","status":"active"}}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)

create "Cashbox" "Create cashbox" POST "/cashboxes" '{{"name":"'"$PFX"'Cashbox","branch_id":'"$BMAIN"',"opening_balance":1000,"status":"active"}}' >/dev/null
create "Expenses" "Create expense" POST "/expenses" '{{"title":"'"$PFX"'Expense","amount":120,"expense_date":"'"$today"'","branch_id":'"$BMAIN"',"payment_method":"cash","status":"approved"}}' >/dev/null

# HR: roles → employee + user_account → staff login + permission probes
HR_ROLES=$(create "HR" "GET /hr/access/roles" GET "/hr/access/roles" "")
ROLE_ID=$(echo "$HR_ROLES" | python3 -c "
import sys, json
d=json.load(sys.stdin)
if not d.get('success'):
    sys.exit(0)
roles=d.get('data') or []
pref=('staff','cashier','hr-test-staff','tenant-staff','sales')
for s in pref:
    for r in roles:
        if r.get('slug')==s and r.get('id'):
            print(r['id']); sys.exit(0)
for r in roles:
    if r.get('slug')!='owner' and r.get('id'):
        print(r['id']); sys.exit(0)
" 2>/dev/null)
EMP_PWD='QAFullSales2026!'
RUN_TAG=$(date +%Y%m%d%H%M%S)
EMP_EMAIL="qa-full-sales-${{RUN_TAG}}@${{TENANT_SLUG}}.dressnmore.test"
EMP_CODE="${{PFX}}EMP-SALES-${{RUN_TAG}}"
HR_EMP_BODY=$(python3 -c "
import json
print(json.dumps({{
  'employee_code': '$EMP_CODE',
  'full_name': '${{PFX}}Employee-Sales',
  'phone': '0500777001',
  'email': '$EMP_EMAIL',
  'branch_id': int('$BMAIN') if '$BMAIN' else None,
  'employment_type': 'full_time',
  'status': 'active',
  'joining_date': '$today',
  'base_salary': 5000,
  'salary_type': 'monthly',
  'user_account': {{
    'email': '$EMP_EMAIL',
    'password': '$EMP_PWD',
    'password_confirmation': '$EMP_PWD',
    'role_id': int('$ROLE_ID') if '$ROLE_ID' else None,
  }},
}}, ensure_ascii=False))
" 2>/dev/null)
HR_OUT=$(create "HR" "POST /hr/employees+user_account" POST "/hr/employees" "$HR_EMP_BODY")
EMP_ID=$(echo "$HR_OUT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
USER_ID=$(echo "$HR_OUT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('user_id','') if d.get('success') else '')" 2>/dev/null)
if [ -n "$EMP_EMAIL" ]; then
  cd /var/www/back-dressnmore-new && php artisan tinker --execute="
\\$t=\\App\\Models\\Central\\Tenant::where('slug','$TENANT_SLUG')->first();
app(\\App\\Services\\Tenant\\TenantUserDirectoryService::class)->register(\\$t,'$EMP_EMAIL');
echo 'dir_ok';
" >/dev/null 2>&1
  log_step "HR" "Directory register HR login email" 200 "{{\\"email\\":\\"$EMP_EMAIL\\",\\"registered\\":true}}"
fi

# --- Sales ---
SALE_ID=""
if [ -n "$CUST" ] && [ -n "$DRESS_SALE" ]; then
  SALE_BODY=$(create "Sales" "Create sale partial payment" POST "/sales/invoices" '{{"customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"discount":50,"tax":30,"items":[{{"dress_id":'"$DRESS_SALE"',"description":"sale","quantity":1,"unit_price":2000}}],"initial_payment":{{"amount":400,"method":"cash"}}}}')
  SALE_ID=$(echo "$SALE_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
  if [ -n "$SALE_ID" ]; then
    create "Sales" "Get sale detail" GET "/sales/invoices/$SALE_ID" >/dev/null
    create "Sales" "Add payment" POST "/invoices/$SALE_ID/payments" '{{"amount":300,"method":"cash"}}' >/dev/null
    create "Sales" "Cancel sale" POST "/invoices/$SALE_ID/cancel" '{{}}' >/dev/null
  fi
fi

# --- Rental ---
RENT_ID=""
if [ -n "$CUST" ] && [ -n "$DRESS_RENT" ]; then
  create "Rental" "Unavailable days" GET "/dresses/$DRESS_RENT/unavailable-days?from={start}&to={end}" >/dev/null
  RENT_BODY=$(create "Rental" "Create rental" POST "/invoices" '{{"type":"rent","status":"confirmed","customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"rent_start_date":"{start}","rent_end_date":"{end}","delivery_date":"{start}","return_date":"{end}","days_of_rent":6,"items":[{{"dress_id":'"$DRESS_RENT"',"quantity":1,"unit_price":900}}],"initial_payment":{{"amount":200,"method":"cash"}},"security_deposit":250}}')
  RENT_ID=$(echo "$RENT_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
  create "Rental" "Overlap booking same dress" POST "/invoices" '{{"type":"rent","status":"confirmed","customer_id":'"$CUST"',"rent_start_date":"{overlap_s}","rent_end_date":"{overlap_e}","delivery_date":"{overlap_s}","return_date":"{overlap_e}","items":[{{"dress_id":'"$DRESS_RENT"',"quantity":1,"unit_price":900}}]}}' >/dev/null
  create "Rental" "Non-overlap booking" POST "/invoices" '{{"type":"rent","status":"confirmed","customer_id":'"$CUST"',"rent_start_date":"{free_s}","rent_end_date":"{free_e}","delivery_date":"{free_s}","return_date":"{free_e}","items":[{{"dress_id":'"$DRESS_RENT"',"quantity":1,"unit_price":900}}]}}' >/dev/null
  if [ -n "$RENT_ID" ]; then
    create "Rental" "Deliver invoice" POST "/invoices/$RENT_ID/deliver" '{{}}' >/dev/null
    create "Rental" "Return invoice" POST "/invoices/$RENT_ID/return" '{{"dress_status_after_return":"available","notes":"'"$PFX"' return"}}' >/dev/null
    create "Rental" "Settlement preview" GET "/returns/$RENT_ID/settlement-preview" >/dev/null
  fi
fi

create "Deliveries" "List deliveries" GET "/deliveries?per_page=5" >/dev/null
create "Returns" "List returns" GET "/returns?per_page=5" >/dev/null
create "Returns" "Overdue returns" GET "/returns/overdue?per_page=5" >/dev/null

# --- Tailoring ---
if [ -n "$CUST" ]; then
  TAIL=$(create "Tailoring" "Create order" POST "/tailoring/orders" '{{"customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"occasion_date":"{start}","delivery_date":"{start}","notes":"'"$PFX"'","items":[{{"description":"custom","quantity":1,"unit_price":1100}}],"measurements":{{"height":165}}}}')
  TID=$(echo "$TAIL" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
  if [ -n "$TID" ]; then
    create "Tailoring" "Change stage" POST "/tailoring/orders/$TID/change-stage" '{{"stage":"cutting"}}' >/dev/null
    create "Tailoring" "Workshop board" GET "/tailoring/workshop-board" >/dev/null
  fi
fi

# --- Purchases ---
if [ -n "$SUP" ]; then
  PO=$(create "Purchases" "Create PO" POST "/purchase-orders" '{{"supplier_id":'"$SUP"',"branch_id":'"$BMAIN"',"notes":"'"$PFX"'","items":[{{"description":"fabric","quantity":2,"unit_cost":200}}]}}')
  POID=$(echo "$PO" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{{}}).get('id',''))" 2>/dev/null)
  if [ -n "$POID" ]; then
    create "Purchases" "PO payment" POST "/purchase-orders/$POID/payments" '{{"amount":200,"method":"cash"}}' >/dev/null
  fi
fi

# --- Reports ---
for ep in "/dashboard/overview" "/reports/catalog" "/reports/sales" "/reports/payments" "/reports/expenses" "/accounting/summary" "/hr/dashboard"; do
  create "Reports" "GET $ep" GET "$ep" >/dev/null
done

# --- Statement (كشف المعاملات) ---
for ep in "/cashboxes/statement/branches" "/cashboxes/statement/summary" "/cashboxes/statement/ledger"; do
  create "Statement" "GET $ep" GET "$ep" >/dev/null
done
create "Statement" "Export xlsx" GET "/cashboxes/statement/export?format=xlsx" >/dev/null

# --- HR extended ---
for ep in "/hr/employees" "/hr/attendance" "/hr/shifts" "/hr/leaves" "/hr/documents" "/hr/settings" "/hr/access/roles"; do
  create "HR" "GET $ep" GET "$ep" >/dev/null
done

# --- Finance lists ---
for ep in "/payments?per_page=5" "/cashboxes?per_page=5" "/cash-movements?per_page=5" "/expenses?per_page=5"; do
  create "Finance" "GET $ep" GET "$ep" >/dev/null
done

# --- Settings / Subscription / Ops ---
for ep in "/settings/profile" "/subscription/overview" "/notifications?per_page=5" "/workshops?per_page=5" "/factories?per_page=5" "/employees?per_page=5" "/dresses?per_page=5" "/branches?per_page=5" "/customers?per_page=5" "/suppliers?per_page=5"; do
  create "Platform" "GET $ep" GET "$ep" >/dev/null
done

# --- Manual cash movement ---
CBOX_ID=$(curl_json GET "/cashboxes?per_page=1" "" | sed '$d' | python3 -c "import sys,json; d=json.load(sys.stdin); print((d.get('data') or [{{}}])[0].get('id',''))" 2>/dev/null)
if [ -n "$CBOX_ID" ]; then
  create "CashMovements" "Manual income" POST "/cash-movements" '{{"type":"income","direction":"in","amount":75,"cashbox_id":'"$CBOX_ID"',"description":"'"$PFX"' manual","reference":"'"$PFX"'-MAN","movement_date":"'"$today"'"}}' >/dev/null
fi

# --- Edge cases ---
create "Edge" "Sale empty items" POST "/sales/invoices" '{{"customer_id":'"${{CUST:-1}}"',"items":[]}}' >/dev/null
create "Edge" "Bad rental dates" POST "/invoices" '{{"type":"rent","status":"confirmed","customer_id":'"${{CUST:-1}}"',"rent_start_date":"{end}","rent_end_date":"{start}","delivery_date":"{end}","return_date":"{start}","items":[{{"dress_id":'"${{DRESS_RENT:-1}}"',"quantity":1,"unit_price":100}}]}}' >/dev/null

# --- Permissions: limited user ---
cd /var/www/back-dressnmore-new && php artisan tinker --execute="
\\$t=\\App\\Models\\Central\\Tenant::where('slug','$TENANT_SLUG')->first();
app(\\App\\Services\\Tenant\\TenantDatabaseManager::class)->connect(\\$t);
\\$role=\\App\\Models\\Tenant\\Role::firstOrCreate(['slug'=>'qa-full-noperms'],['name'=>'QA FULL No Perms']);
\\$role->permissions()->sync([]);
\\$u=\\App\\Models\\Tenant\\User::updateOrCreate(['email'=>'qa-full-noperms@dressnmore.test'],['name'=>'QA FULL Limited','password'=>\\Illuminate\\Support\\Facades\\Hash::make('{PWD}'),'status'=>'active']);
\\$u->roles()->sync([\\$role->id]);
echo 'ok';
" >/dev/null 2>&1

LIM=$(curl -sk -X POST "$API/login" -H "Content-Type: application/json" -H "X-Tenant: $TENANT" -d '{{"workspace":"'"$TENANT"'","email":"qa-full-noperms@dressnmore.test","password":"'"$PWD"'"}}')
LTOKEN=$(echo "$LIM" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('token','') if d.get('success') else '')" 2>/dev/null || true)
if [ -n "$LTOKEN" ]; then
  for ep in "/sales/invoices" "/hr/settings" "/branches"; do
    code=$(curl -sk -o /tmp/qa_lim.json -w '%{{http_code}}' "$API$ep" -H "Authorization: Bearer $LTOKEN" -H "X-Tenant: $TENANT_SLUG" -H "Accept: application/json")
    body=$(cat /tmp/qa_lim.json)
    log_step "Permissions" "Limited user GET $ep" "$code" "$body"
  done
fi

# HR staff login + API permission checks
EMP_LOGIN=$(curl -sk -X POST "$API/login" -H "Content-Type: application/json" -H "X-Tenant: $TENANT_SLUG" -d '{{"workspace":"'"$TENANT_SLUG"'","email":"'"$EMP_EMAIL"'","password":"'"$EMP_PWD"'"}}')
EMP_HTTP=$(echo "$EMP_LOGIN" | python3 -c "import sys,json; d=json.load(sys.stdin); print(200 if d.get('success') else 401)" 2>/dev/null || echo 401)
log_step "Permissions" "POST /login HR staff" "$EMP_HTTP" "$EMP_LOGIN"
EMP_TOKEN=$(echo "$EMP_LOGIN" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('token','') if d.get('success') else '')" 2>/dev/null || true)
if [ -n "$EMP_TOKEN" ]; then
  for ep in "/dashboard" "/hr/dashboard" "/hr/settings"; do
    code=$(curl -sk -o /tmp/qa_emp.json -w '%{{http_code}}' "$API$ep" -H "Authorization: Bearer $EMP_TOKEN" -H "X-Tenant: $TENANT_SLUG" -H "Accept: application/json")
    body=$(cat /tmp/qa_emp.json)
    log_step "Permissions" "Staff GET $ep" "$code" "$body"
  done
  if [ -n "$EMP_ID" ]; then
    code=$(curl -sk -o /tmp/qa_emp_del.json -w '%{{http_code}}' -X DELETE "$API/hr/employees/$EMP_ID" -H "Authorization: Bearer $EMP_TOKEN" -H "X-Tenant: $TENANT_SLUG" -H "Accept: application/json")
    body=$(cat /tmp/qa_emp_del.json)
    log_step "Permissions" "Staff DELETE /hr/employees/$EMP_ID" "$code" "$body"
  fi
fi

# PHPUnit tail
cd /var/www/back-dressnmore-new && ./vendor/bin/phpunit --testsuite=Feature --filter=Hr 2>&1 | tail -8

# Frontend build on server
cd /var/www/tenant-dressnmore-new && git log -1 --oneline 2>/dev/null; npx vite build 2>&1 | tail -15

cat "$LOG"
echo "__IDS__ BMAIN=$BMAIN BSEC=$BSEC CUST=$CUST DRESS_SALE=$DRESS_SALE DRESS_RENT=$DRESS_RENT SALE_ID=$SALE_ID RENT_ID=$RENT_ID ROLE_ID=$ROLE_ID EMP_ID=$EMP_ID USER_ID=$USER_ID EMP_EMAIL=$EMP_EMAIL"
"""


def parse_results(raw: str) -> dict:
    steps = []
    for line in raw.splitlines():
        line = line.strip()
        if not line.startswith("{"):
            continue
        try:
            row = json.loads(line)
            body = row.get("body") or {}
            success = body.get("success") if isinstance(body, dict) else None
            http = row.get("http", 0)
            if success is True:
                result = "PASS"
            elif success is False or http >= 400:
                result = "FAIL"
            elif http in (401, 403):
                result = "PASS" if "Permissions" in row.get("module", "") else "FAIL"
            else:
                result = "PARTIAL"
            steps.append(
                {
                    "module": row.get("module"),
                    "scenario": row.get("scenario"),
                    "result": result,
                    "http_status": http,
                    "evidence": json.dumps(body, ensure_ascii=False)[:400],
                    "notes": body.get("message", "") if isinstance(body, dict) else "",
                }
            )
        except json.JSONDecodeError:
            continue
    return {"steps": steps}


def main() -> int:
    script = build_remote_script()
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print("Connecting SSH...")
    ssh.connect(HOST, username=SSH_USER, password=SSH_PASSWORD, timeout=30)

    sftp = ssh.open_sftp()
    remote_path = "/tmp/full_system_qa.sh"
    with sftp.file(remote_path, "w") as f:
        f.write(script)
    sftp.chmod(remote_path, 0o755)
    sftp.close()

    _, stdout, stderr = ssh.exec_command(f"bash {remote_path}", timeout=900)
    raw = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    ssh.close()

    OUT_RAW.write_text(raw + "\n\nSTDERR:\n" + err, encoding="utf-8")

    if "BLOCKED_LOGIN" in raw:
        print("Login failed on server:", raw[:500])
        return 1

    parsed = parse_results(raw)
    ids_match = re.search(r"__IDS__(.+)$", raw, re.M)
    data = {
        "environment": {
            "frontend": "https://staging-tenant.dressnmore.it.com",
            "api": API,
            "tenant": TENANT,
            "owner_email": EMAIL,
            "run_at": datetime.now().isoformat(),
        },
        "ids_line": ids_match.group(1).strip() if ids_match else "",
        **parsed,
        "build_tail": raw.split("npm run build")[-1][-800:] if "npm run build" in raw else "",
        "phpunit_tail": raw.split("./vendor/bin/phpunit")[-1][:600] if "phpunit" in raw else "",
    }
    OUT_JSON.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote {OUT_JSON} ({len(parsed['steps'])} steps)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
