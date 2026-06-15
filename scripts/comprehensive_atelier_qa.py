#!/usr/bin/env python3
"""Deploy fixes + run full atelier QA on staging. Writes Arabic report."""
from __future__ import annotations

import json
import os
import re
import sys
from collections import Counter
from datetime import date, datetime, timedelta
from pathlib import Path

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HOST = "159.198.74.223"
SSH_USER = "root"
SSH_PASSWORD = "RBZ4cjZE184wOx37ip"
API = "https://staging-api.dressnmore.it.com/api/tenant"
LOGIN_TENANT = "qa-staging"
EMAIL = "qa-staging@dressnmore.test"
PWD = "StagingQA2026!"
BACKEND = "/var/www/back-dressnmore-new"
REPO = Path(__file__).resolve().parents[1]
OUT_JSON = REPO / "docs" / "comprehensive-atelier-qa-results.json"
OUT_MD = REPO / "docs" / "comprehensive-atelier-qa-report.md"
OUT_RAW = REPO / "docs" / "comprehensive-atelier-qa-raw.log"

DEPLOY_FILES = [
    "app/Services/Platform/TenantSubscriptionBillingService.php",
    "app/Support/Reports/TabularExport.php",
    "app/Http/Controllers/Tenant/JournalEntryController.php",
    "app/Http/Controllers/Tenant/InvoiceController.php",
    "app/Http/Controllers/Tenant/CashboxController.php",
    "app/Http/Controllers/Tenant/HrPayrollController.php",
    "app/Http/Controllers/Tenant/HrPayrollAdjustmentController.php",
    "app/Http/Controllers/Tenant/TransactionStatementController.php",
    "app/Services/Tenant/HrPayrollService.php",
    "app/Services/Tenant/HrPayrollAdjustmentService.php",
    "app/Services/Tenant/TransactionStatementService.php",
    "app/Http/Requests/Tenant/Cashbox/CloseStatementPeriodRequest.php",
    "app/Http/Requests/Tenant/Hr/Payroll/StoreHrPayrollAdjustmentRequest.php",
    "app/Models/Tenant/HrPayrollAdjustment.php",
    "database/migrations/tenant/2026_06_14_630000_add_category_columns_to_purchase_order_items_table.php",
    "database/migrations/tenant/2026_06_02_620000_create_hr_payroll_adjustments_table.php",
    "routes/api/tenant.php",
]


def build_qa_script() -> str:
    start = (date.today() + timedelta(days=30)).isoformat()
    end = (date.today() + timedelta(days=35)).isoformat()
    today = date.today().isoformat()
    month = date.today().strftime("%Y-%m")

    return f"""#!/bin/bash
set -uo pipefail
API="{API}"
LOGIN_TENANT="{LOGIN_TENANT}"
EMAIL="{EMAIL}"
PWD='{PWD}'
today="{today}"
month="{month}"
LOG=/tmp/atelier_qa.jsonl
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
      -H "Accept: application/json" -H "Content-Type: application/json" -d "$data")
  else
    resp=$(curl -sk -w '\\n__HTTP__:%{{http_code}}' -X "$method" "$API$path" \\
      -H "Authorization: Bearer $TOKEN" -H "X-Tenant: $TENANT_SLUG" -H "Accept: application/json")
  fi
  http=$(echo "$resp" | tail -1 | sed 's/__HTTP__://')
  body=$(echo "$resp" | sed '$d')
  echo "$body"
  echo "$http"
}}

export_file() {{
  local module="$1" scenario="$2" path="$3"
  code=$(curl -sk -o /tmp/qa_export.bin -w '%{{http_code}}' -H "Authorization: Bearer $TOKEN" -H "X-Tenant: $TENANT_SLUG" "$API$path")
  size=$(wc -c < /tmp/qa_export.bin)
  ok="false"
  if [ "$code" = "200" ] && [ "$size" -gt 300 ]; then ok="true"; fi
  log_step "$module" "$scenario" "$code" "{{\\"size\\":$size,\\"ok\\":$ok}}"
}}

create() {{
  local module="$1" scenario="$2" method="$3" path="$4" data="${{5:-}}"
  out=$(curl_json "$method" "$path" "$data")
  http=$(echo "$out" | tail -1)
  body=$(echo "$out" | sed '$d')
  log_step "$module" "$scenario" "$http" "$body"
  echo "$body"
}}

# --- Login ---
login=$(curl -sk -X POST "$API/login" -H "Content-Type: application/json" -H "X-Tenant: $LOGIN_TENANT" \\
  -d '{{"email":"'"$EMAIL"'","password":"'"$PWD"'"}}')
TOKEN=$(echo "$login" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('token','') if d.get('success') else '')" 2>/dev/null || true)
if [ -z "$TOKEN" ]; then echo "BLOCKED_LOGIN:$login"; exit 2; fi
TENANT_SLUG=$(echo "$login" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('tenant',{{}}).get('slug','') or '$LOGIN_TENANT')" 2>/dev/null)
log_step "Auth" "Owner login" 200 "$login"
RUN=$(date +%Y%m%d%H%M%S)
PFX="QA-ATR-$RUN"

# --- 1) Branch ---
BMAIN=$(create "Branch" "Create main branch" POST "/branches" '{{"name":"'"$PFX"'-Main","code":"'"$PFX"'M","phone":"0500100100","address":"فرع رئيسي QA","status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)

# --- 2) Supplier ---
SUP=$(create "Supplier" "Create supplier" POST "/suppliers" '{{"name":"'"$PFX"'-Supplier","phone":"0500200200","status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)

# --- 3) Category + subcategory (parent_id) ---
CAT=$(create "Catalog" "Create parent category" POST "/dress-categories" '{{"name":"'"$PFX"'-Cat","status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
SUB=""
if [ -n "$CAT" ]; then
  SUB=$(create "Catalog" "Create subcategory parent_id" POST "/dress-categories" '{{"name":"'"$PFX"'-Sub","parent_id":'"$CAT"',"status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
fi

# --- Customer + Cashbox ---
CUST=$(create "Customer" "Create customer" POST "/customers" '{{"name":"'"$PFX"'-Customer","phone":"0500300300","source":"walk_in","status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
CBOX=$(create "Cashbox" "Create cashbox" POST "/cashboxes" '{{"name":"'"$PFX"'-Cashbox","branch_id":'"$BMAIN"',"opening_balance":2000,"status":"active"}}' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)

# --- 4) Purchase order + receive -> inventory ---
PO_ID=""
DRESS_ID=""
if [ -n "$SUP" ] && [ -n "$BMAIN" ] && [ -n "$CAT" ]; then
  PO_JSON=$(python3 - <<PY
import json
sub = "$SUB"
print(json.dumps({{
  "supplier_id": int("$SUP"),
  "branch_id": int("$BMAIN"),
  "category_id": int("$CAT"),
  "subcategory_id": int(sub) if sub else None,
  "notes": "$PFX PO",
  "items": [{{
    "item_name": "قماش فاخر",
    "description": "بند PO",
    "quantity": 2,
    "unit_price": 450,
    "dress_category_id": int("$CAT"),
    "dress_subcategory_id": int(sub) if sub else None,
  }}],
}}, ensure_ascii=False))
PY
)
  PO_BODY=$(create "Purchase" "Create PO" POST "/purchase-orders" "$PO_JSON")
  PO_ID=$(echo "$PO_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
  if [ -n "$PO_ID" ]; then
    create "Purchase" "Receive PO -> inventory" POST "/purchase-orders/$PO_ID/receive" '{{}}' >/dev/null
    DRESSES=$(create "Inventory" "List dresses after PO" GET "/dresses?search=PO-&per_page=10" "")
    DRESS_ID=$(echo "$DRESSES" | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[0]['id'] if data else '')" 2>/dev/null)
    if [ -n "$DRESS_ID" ]; then
      DRESS_CODE=$(echo "$DRESSES" | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[0].get('code','') if data else '')" 2>/dev/null)
      create "Inventory" "Set sale/rent prices" PUT "/dresses/$DRESS_ID" '{{"code":"'"$DRESS_CODE"'","branch_id":'"$BMAIN"',"dress_category_id":'"$CAT"',"dress_subcategory_id":'"$SUB"',"sale_price":2800,"rental_price":950,"status":"available"}}' >/dev/null
    fi
  fi
fi

# --- 5) Sale invoice ---
SALE_ID=""
if [ -n "$CUST" ] && [ -n "$DRESS_ID" ]; then
  SALE_BODY=$(create "Sales" "Create sale invoice" POST "/sales/invoices" '{{"customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"items":[{{"dress_id":'"$DRESS_ID"',"description":"بيع QA","quantity":1,"unit_price":2800}}],"discount":0,"tax":0,"initial_payment":{{"amount":500,"method":"cash","cashbox_id":'"${{CBOX:-null}}"'}}}}')
  SALE_ID=$(echo "$SALE_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
fi

# --- 6) Rental + deliver + return ---
RENT_ID=""
if [ -n "$CUST" ] && [ -n "$DRESS_ID" ]; then
  RENT_BODY=$(create "Rental" "Create rental invoice" POST "/invoices" '{{"type":"rent","status":"confirmed","customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"rent_start_date":"{start}","rent_end_date":"{end}","delivery_date":"{start}","return_date":"{end}","days_of_rent":6,"items":[{{"dress_id":'"$DRESS_ID"',"quantity":1,"unit_price":950}}],"initial_payment":{{"amount":200,"method":"cash"}},"security_deposit":300}}')
  RENT_ID=$(echo "$RENT_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
  if [ -n "$RENT_ID" ]; then
    create "Rental" "Deliver dress" POST "/invoices/$RENT_ID/deliver" '{{}}' >/dev/null
    create "Rental" "Return dress" POST "/invoices/$RENT_ID/return" '{{"dress_status_after_return":"available","notes":"'"$PFX"' return"}}' >/dev/null
  fi
fi

# --- 7) Tailoring ---
TAIL_ID=""
if [ -n "$CUST" ]; then
  TAIL_BODY=$(create "Tailoring" "Create tailoring order" POST "/tailoring/orders" '{{"customer_id":'"$CUST"',"branch_id":'"$BMAIN"',"occasion_datetime":"{start}","items":[{{"description":"فستان تفصيل QA","quantity":1,"unit_price":3200}}],"measurements":[{{"label":"الطول","value":"165","unit":"cm"}},{{"label":"الصدر","value":"90","unit":"cm"}}],"initial_payment":{{"amount":800,"method":"cash"}}}}')
  TAIL_ID=$(echo "$TAIL_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
fi

# --- 8) HR employee + salary advance + payroll ---
EMP_ID=""
ROLE_ID=$(create "HR" "GET roles" GET "/hr/access/roles" "" | python3 -c "
import sys,json
d=json.load(sys.stdin)
for r in d.get('data') or []:
  if r.get('slug')!='owner' and r.get('id'):
    print(r['id']); break
" 2>/dev/null)
EMP_EMAIL="qa-atr-$RUN@${{TENANT_SLUG}}.dressnmore.test"
EMP_PWD='QAAtelier2026!'
if [ -n "$ROLE_ID" ]; then
  HR_JSON=$(python3 - <<PY
import json
print(json.dumps({{
  "employee_code": "$PFX-EMP",
  "full_name": "$PFX Employee",
  "phone": "0500400400",
  "email": "$EMP_EMAIL",
  "branch_id": int("$BMAIN") if "$BMAIN" else None,
  "employment_type": "full_time",
  "status": "active",
  "joining_date": "$today",
  "base_salary": 6000,
  "salary_type": "monthly",
  "user_account": {{
    "email": "$EMP_EMAIL",
    "password": "$EMP_PWD",
    "password_confirmation": "$EMP_PWD",
    "role_id": int("$ROLE_ID"),
  }},
}}, ensure_ascii=False))
PY
)
  HR_BODY=$(create "HR" "Create employee" POST "/hr/employees" "$HR_JSON")
  EMP_ID=$(echo "$HR_BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('id','') if d.get('success') else '')" 2>/dev/null)
  if [ -n "$EMP_ID" ]; then
    cd {BACKEND} && php artisan tinker --execute="
\\$t=\\App\\Models\\Central\\Tenant::where('slug','$TENANT_SLUG')->first();
app(\\App\\Services\\Tenant\\TenantUserDirectoryService::class)->register(\\$t,'$EMP_EMAIL');
" >/dev/null 2>&1
    create "HR" "Add salary advance" POST "/hr/payroll/adjustments" '{{"employee_id":'"$EMP_ID"',"type":"advance","amount":500,"month":"'"$month"'","notes":"'"$PFX"' advance"}}' >/dev/null
    create "HR" "Payroll sheet" GET "/hr/payroll?month=$month" "" >/dev/null
    create "HR" "Employee payslip" GET "/hr/payroll/employees/$EMP_ID/payslip?month=$month" "" >/dev/null
  fi
fi

# --- 9) Manual journal entry ---
ACCOUNTS=$(create "Accounting" "List accounts" GET "/accounting/journal-entries/accounts" "")
A1=$(echo "$ACCOUNTS" | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[0]['id'] if data else '')" 2>/dev/null)
A2=$(echo "$ACCOUNTS" | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[1]['id'] if len(data)>1 else '')" 2>/dev/null)
if [ -n "$A1" ] && [ -n "$A2" ]; then
  create "Accounting" "Manual journal entry" POST "/accounting/journal-entries" '{{"entry_date":"'"$today"'","description":"'"$PFX"' manual entry","branch_id":'"$BMAIN"',"lines":[{{"account_id":'"$A1"',"debit":100,"credit":0}},{{"account_id":'"$A2"',"debit":0,"credit":100}}]}}' >/dev/null
fi
create "Accounting" "Journal summary" GET "/accounting/journal-entries/summary" "" >/dev/null

# --- 10) Statement (كشف المعاملات) ---
create "Statement" "Branch summaries" GET "/cashboxes/statement/branches" "" >/dev/null
create "Statement" "Ledger" GET "/cashboxes/statement/ledger" "" >/dev/null
export_file "Statement" "Export PDF" "/cashboxes/statement/export?format=pdf"
export_file "Statement" "Export Excel" "/cashboxes/statement/export?format=xlsx"

# --- 11) Reports exports ---
for rpt in sales rental tailoring payments expenses cash accounting; do
  export_file "Reports" "PDF $rpt" "/reports/$rpt?export=pdf"
  export_file "Reports" "Excel $rpt" "/reports/$rpt?export=xlsx"
done

# --- 12) Module exports pdf/xlsx ---
export_file "Exports" "Invoices PDF" "/invoices/export?format=pdf"
export_file "Exports" "Invoices Excel" "/invoices/export?format=xlsx"
export_file "Exports" "Cashboxes PDF" "/cashboxes/export?format=pdf"
export_file "Exports" "Cashboxes Excel" "/cashboxes/export?format=xlsx"
export_file "Exports" "Journal PDF" "/accounting/journal-entries/export?format=pdf"
export_file "Exports" "Journal Excel" "/accounting/journal-entries/export?format=xlsx"

# --- 13) Settings + Subscription + Profile ---
USER_EMAIL=$(echo "$login" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{{}}).get('user',{{}}).get('email','') or '$EMAIL')" 2>/dev/null)
create "Settings" "GET profile" GET "/settings/profile" "" >/dev/null
create "Settings" "UPDATE profile name" PUT "/settings/profile" '{{"name":"QA Staging Tester '"$RUN"'","email":"'"$USER_EMAIL"'"}}' >/dev/null
create "Subscription" "Overview" GET "/subscription/overview" "" >/dev/null
create "Subscription" "Payment gateways" GET "/subscription/payment-gateways" "" >/dev/null

# --- Lists sanity ---
for ep in "/dashboard/overview" "/deliveries?per_page=3" "/returns?per_page=3" "/payments?per_page=3" "/expenses?per_page=3"; do
  create "Lists" "GET $ep" GET "$ep" "" >/dev/null
done

cat "$LOG"
echo "__IDS__ RUN=$RUN BMAIN=$BMAIN SUP=$SUP CAT=$CAT SUB=$SUB CUST=$CUST CBOX=$CBOX PO=$PO_ID DRESS=$DRESS_ID SALE=$SALE_ID RENT=$RENT_ID TAIL=$TAIL_ID EMP=$EMP_ID"
"""


def parse_steps(raw: str) -> list[dict]:
    steps = []
    for line in raw.splitlines():
        line = line.strip()
        if not line.startswith("{"):
            continue
        try:
            row = json.loads(line)
        except json.JSONDecodeError:
            continue
        body = row.get("body") or {}
        http = int(row.get("http", 0))
        module = row.get("module", "")
        scenario = row.get("scenario", "")

        if module == "Export":
            ok = bool(body.get("ok")) if isinstance(body, dict) else False
            result = "PASS" if ok and http == 200 else "FAIL"
        elif isinstance(body, dict) and body.get("success") is True:
            result = "PASS"
        elif isinstance(body, dict) and body.get("success") is False:
            result = "FAIL" if http >= 400 else "PARTIAL"
        elif http in (200, 201):
            result = "PASS"
        elif http in (401, 403) and "Permissions" in module:
            result = "PASS"
        else:
            result = "FAIL" if http >= 400 else "PARTIAL"

        steps.append({
            "module": module,
            "scenario": scenario,
            "result": result,
            "http_status": http,
            "notes": body.get("message", "") if isinstance(body, dict) else "",
            "evidence": json.dumps(body, ensure_ascii=False)[:350],
        })
    return steps


def write_report(data: dict) -> None:
    steps = data["steps"]
    counts = Counter(s["result"] for s in steps)
    fails = [s for s in steps if s["result"] == "FAIL"]

    lines = [
        "# تقرير الاختبار الشامل — أتيليه (جولة 2)",
        "",
        f"**التاريخ:** {datetime.now().strftime('%Y-%m-%d %H:%M')}",
        f"**Tenant:** {data.get('tenant_slug', '')}",
        f"**الحساب:** {EMAIL}",
        "",
        "## الملخص",
        "",
        f"| PASS | FAIL | PARTIAL | المجموع |",
        f"|------|------|---------|---------|",
        f"| {counts.get('PASS', 0)} | {counts.get('FAIL', 0)} | {counts.get('PARTIAL', 0)} | {len(steps)} |",
        "",
        f"**نسبة النجاح:** {round(100 * counts.get('PASS', 0) / max(len(steps), 1))}%",
        "",
        f"**المعرّفات:** `{data.get('ids_line', '')}`",
        "",
        "## سير العمل",
        "",
        "1. فرع → مورد → طلبية شراء → استلام → مخزون",
        "2. فاتورة بيع → إيجار → تسليم → إرجاع",
        "3. تفصيل → موظف → سلفة → كشف رواتب",
        "4. قيود محاسبية → كشف معاملات → تقارير",
        "5. تصدير PDF/Excel لكل الوحدات",
        "6. البروفايل + الاشتراك + الإعدادات",
        "",
        "## النتائج حسب المديول",
        "",
        "| المديول | السيناريو | النتيجة | HTTP |",
        "|---------|-----------|---------|------|",
    ]
    for s in steps:
        lines.append(f"| {s['module']} | {s['scenario']} | {s['result']} | {s.get('http_status', '')} |")

    if fails:
        lines.extend(["", "## الأخطاء", ""])
        for s in fails:
            lines.append(f"- **{s['module']} / {s['scenario']}** — {s.get('notes', s.get('evidence', ''))[:120]}")

    lines.extend([
        "",
        "## التوصية",
        "",
        "جاهز للتشغيل الداخلي" if counts.get("FAIL", 0) <= 3 else "يحتاج إصلاحات قبل الإنتاج",
        "",
        f"*الأدلة:* `{OUT_JSON.name}`",
    ])
    OUT_MD.write_text("\n".join(lines), encoding="utf-8")


def deploy_and_run() -> int:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print("Connecting SSH...")
    ssh.connect(HOST, username=SSH_USER, password=SSH_PASSWORD, timeout=30)

    sftp = ssh.open_sftp()

    def ensure_remote_dir(path: str) -> None:
        if not path.startswith("/"):
            return
        current = ""
        for part in [p for p in path.split("/") if p]:
            current += "/" + part
            try:
                sftp.stat(current)
            except OSError:
                sftp.mkdir(current)

    for rel in DEPLOY_FILES:
        local = REPO / rel
        remote = f"{BACKEND}/{rel.replace(chr(92), '/')}"
        if not local.is_file():
            print("MISSING", rel)
            continue
        ensure_remote_dir("/".join(remote.split("/")[:-1]))
        print("UPLOAD", rel)
        sftp.put(str(local.resolve()), remote)
    sftp.close()

    cmds = [
        f"cd {BACKEND} && php artisan route:clear && php artisan config:clear && php artisan cache:clear",
        f"cd {BACKEND} && php staging_migrate_all_tenants.php 2>/dev/null || php -r \"require 'vendor/autoload.php'; \\$app=require 'bootstrap/app.php'; \\$app->make(Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap(); foreach(App\\\\Models\\\\Central\\\\Tenant::where('status','active')->get() as \\$t) {{ try {{ app(App\\\\Services\\\\Platform\\\\TenantProvisioningService::class)->migrate(\\$t); echo \\$t->slug.' ok '; }} catch(Throwable \\$e) {{ echo \\$t->slug.' fail '; }} }}\"",
    ]
    for cmd in cmds:
        print("RUN", cmd[:100])
        ssh.exec_command(cmd, timeout=600)

    script = build_qa_script()
    remote_qa = "/tmp/atelier_qa.sh"
    sftp = ssh.open_sftp()
    with sftp.file(remote_qa, "w") as f:
        f.write(script)
    sftp.chmod(remote_qa, 0o755)
    sftp.close()

    _, stdout, stderr = ssh.exec_command(f"bash {remote_qa}", timeout=900)
    raw = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    ssh.close()

    OUT_RAW.write_text(raw + "\n\nSTDERR:\n" + err, encoding="utf-8")

    if "BLOCKED_LOGIN" in raw:
        print("LOGIN FAILED")
        return 1

    steps = parse_steps(raw)
    ids_match = re.search(r"__IDS__(.+)$", raw, re.M)
    tenant_slug = ""
    for line in raw.splitlines():
        if '"slug"' in line and "alhatom" in line:
            m = re.search(r'"slug":\s*"([^"]+)"', line)
            if m:
                tenant_slug = m.group(1)
                break

    data = {
        "run_at": datetime.now().isoformat(),
        "tenant_slug": tenant_slug,
        "owner_email": EMAIL,
        "ids_line": ids_match.group(1).strip() if ids_match else "",
        "steps": steps,
        "summary": dict(Counter(s["result"] for s in steps)),
    }
    OUT_JSON.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    write_report(data)

    print(f"Steps: {len(steps)} Summary: {data['summary']}")
    print(f"Wrote {OUT_MD}")
    return 0 if data["summary"].get("FAIL", 0) == 0 else 2


if __name__ == "__main__":
    raise SystemExit(deploy_and_run())
