#!/bin/bash
# Production clean build full smoke — reads passwords from secrets file only
set -euo pipefail
PROD=/var/www/dressnmore-production/backend
SECRETS=/root/.dressnmore-production-bootstrap.secrets
RESULT=/var/www/dressnmore-production/smoke-results.jsonl
API=http://127.0.0.1:18099
: > "$RESULT"

if [ ! -f "$SECRETS" ]; then
  echo "MISSING_SECRETS"
  exit 1
fi

# Parse secrets safely (no source — passwords may contain shell metacharacters)
while IFS='=' read -r k v; do
  case "$k" in
    PLATFORM_ADMIN_EMAIL) PLATFORM_ADMIN_EMAIL="$v" ;;
    PLATFORM_ADMIN_PASSWORD) PLATFORM_ADMIN_PASSWORD="$v" ;;
    TENANT_SLUG) TENANT_SLUG="$v" ;;
    TENANT_OWNER_EMAIL) TENANT_OWNER_EMAIL="$v" ;;
    TENANT_OWNER_PASSWORD) TENANT_OWNER_PASSWORD="$v" ;;
  esac
done < "$SECRETS"

pkill -f 'artisan serve.*18099' 2>/dev/null || true
cd "$PROD"
php artisan serve --host=127.0.0.1 --port=18099 >/dev/null 2>&1 &
sleep 2

record() {
  local area="$1" check="$2" result="$3" http="$4" note="$5"
  python3 -c "import json,sys; print(json.dumps({'area':sys.argv[1],'check':sys.argv[2],'result':sys.argv[3],'http':sys.argv[4],'note':sys.argv[5][:400]}, ensure_ascii=False))" "$area" "$check" "$result" "$http" "$note" >> "$RESULT"
}

has_stack() {
  echo "$1" | python3 -c "import sys,re; b=sys.stdin.read(); print('yes' if re.search(r'SQLSTATE|Stack trace|vendor/|Traceback', b, re.I) else 'no')" 2>/dev/null || echo no
}

call() {
  local method="$1" path="$2" data="${3:-}" auth="${4:-}" tenant="${5:-}"
  local args=(-sk -w '\n__HTTP__:%{http_code}' -X "$method" "${API}${path}" -H 'Accept: application/json')
  [ -n "$auth" ] && args+=(-H "Authorization: Bearer $auth")
  [ -n "$tenant" ] && args+=(-H "X-Tenant: $tenant")
  if [ -n "$data" ]; then
    args+=(-H 'Content-Type: application/json' -d "$data")
  fi
  curl "${args[@]}"
}

# --- Security env ---
cd "$PROD"
for kv in "APP_ENV=production" "APP_DEBUG=false" "LOG_LEVEL=info" "SUBSCRIPTION_ALLOW_MOCK_PAYMENTS=false"; do
  k=${kv%%=*}; v=${kv#*=}
  actual=$(grep "^${k}=" .env | cut -d= -f2- | tr -d '"')
  if [ "$actual" = "$v" ]; then r=PASS; else r=FAIL; fi
  record security "env $k" "$r" 0 "$actual"
done

# --- Platform login ---
PLAT=$(call POST /api/platform/login "{\"email\":\"$PLATFORM_ADMIN_EMAIL\",\"password\":\"$PLATFORM_ADMIN_PASSWORD\"}")
PH=$(echo "$PLAT" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
PB=$(echo "$PLAT" | sed '/__HTTP__:/d')
PT=$(echo "$PB" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null || true)
[ -n "$PT" ] && [ "$PH" = "200" ] && record platform "admin login" PASS "$PH" "token ok" || record platform "admin login" FAIL "$PH" "$(echo "$PB" | head -c 120)"

TENANTS=$(call GET /api/platform/tenants "" "$PT")
TH=$(echo "$TENANTS" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
TB=$(echo "$TENANTS" | sed '/__HTTP__:/d')
TC=$(echo "$TB" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('meta',{}).get('total', len(d.get('data',[]))))" 2>/dev/null || echo 0)
[ "$TH" = "200" ] && [ "${TC:-0}" -ge 1 ] && record platform "tenants list" PASS "$TH" "total=$TC" || record platform "tenants list" FAIL "$TH" "$(echo "$TB" | head -c 120)"

TSHOW=$(call GET "/api/platform/tenants?search=$TENANT_SLUG" "" "$PT")
TSH=$(echo "$TSHOW" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
[ "$TSH" = "200" ] && record platform "tenant show/search" PASS "$TSH" "slug=$TENANT_SLUG" || record platform "tenant show/search" FAIL "$TSH" ""

# --- Tenant login ---
TLOGIN=$(call POST /api/tenant/login "{\"email\":\"$TENANT_OWNER_EMAIL\",\"password\":\"$TENANT_OWNER_PASSWORD\"}" "" "$TENANT_SLUG")
TLH=$(echo "$TLOGIN" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
TLB=$(echo "$TLOGIN" | sed '/__HTTP__:/d')
TT=$(echo "$TLB" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null || true)
[ -n "$TT" ] && [ "$TLH" = "200" ] && record tenant "owner login" PASS "$TLH" ok || record tenant "owner login" FAIL "$TLH" "$(echo "$TLB" | head -c 120)"

DASH=$(call GET /api/tenant/dashboard/overview "" "$TT" "$TENANT_SLUG")
DH=$(echo "$DASH" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
[ "$DH" = "200" ] && record tenant "dashboard overview" PASS "$DH" ok || record tenant "dashboard overview" FAIL "$DH" "$(echo "$DASH" | sed '/__HTTP__:/d' | head -c 120)"

SUB=$(call GET /api/tenant/subscription/overview "" "$TT" "$TENANT_SLUG")
SH=$(echo "$SUB" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
[ "$SH" = "200" ] && record tenant "subscription overview" PASS "$SH" ok || record tenant "subscription overview" FAIL "$SH" "$(echo "$SUB" | sed '/__HTTP__:/d' | head -c 120)"

BR=$(call POST /api/tenant/branches "{\"name\":\"Prod Smoke Branch\",\"phone\":\"0500000001\",\"is_main\":true}" "$TT" "$TENANT_SLUG")
BH=$(echo "$BR" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
BID=$(echo "$BR" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
[ "$BH" = "201" ] && [ -n "$BID" ] && record tenant "branch create" PASS "$BH" "id=$BID" || record tenant "branch create" FAIL "$BH" ""

BGR=$(call GET "/api/tenant/branches?search=Prod" "" "$TT" "$TENANT_SLUG")
[ "$(echo "$BGR" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "200" ] && record tenant "branch read" PASS 200 ok || record tenant "branch read" FAIL 0 ""

CU=$(call POST /api/tenant/customers "{\"name\":\"Prod Smoke Customer\",\"phone\":\"0500000002\"}" "$TT" "$TENANT_SLUG")
CID=$(echo "$CU" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
[ "$(echo "$CU" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "201" ] && record tenant "customer create" PASS 201 "id=$CID" || record tenant "customer create" FAIL 0 ""

CUG=$(call GET "/api/tenant/customers?search=Prod" "" "$TT" "$TENANT_SLUG")
[ "$(echo "$CUG" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "200" ] && record tenant "customer read" PASS 200 ok || record tenant "customer read" FAIL 0 ""

SU=$(call POST /api/tenant/suppliers "{\"name\":\"Prod Smoke Supplier\",\"phone\":\"0500000003\"}" "$TT" "$TENANT_SLUG")
SID=$(echo "$SU" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
[ "$(echo "$SU" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "201" ] && record tenant "supplier create" PASS 201 ok || record tenant "supplier create" FAIL 0 ""

SUG=$(call GET "/api/tenant/suppliers?search=Prod" "" "$TT" "$TENANT_SLUG")
[ "$(echo "$SUG" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "200" ] && record tenant "supplier read" PASS 200 ok || record tenant "supplier read" FAIL 0 ""

CB=$(call POST /api/tenant/cashboxes "{\"name\":\"Prod Smoke Box\",\"branch_id\":$BID,\"opening_balance\":1000}" "$TT" "$TENANT_SLUG")
CBID=$(echo "$CB" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
[ "$(echo "$CB" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "201" ] && record tenant "cashbox create" PASS 201 ok || record tenant "cashbox create" FAIL 0 ""

CBG=$(call GET "/api/tenant/cashboxes?search=Prod" "" "$TT" "$TENANT_SLUG")
[ "$(echo "$CBG" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')" = "200" ] && record tenant "cashbox read" PASS 200 ok || record tenant "cashbox read" FAIL 0 ""

# Inventory chain for sale/rental (tolerate partial failures)
set +e
CAT=$(call POST /api/tenant/dress-categories "{\"name\":\"Prod Cat Smoke\"}" "$TT" "$TENANT_SLUG")
CATID=$(echo "$CAT" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
SCAT=$(call POST /api/tenant/dress-categories "{\"name\":\"Prod Sub Smoke\",\"parent_id\":${CATID:-0}}" "$TT" "$TENANT_SLUG")
SCATID=$(echo "$SCAT" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
PO=$(call POST /api/tenant/purchase-orders "{\"supplier_id\":$SID,\"branch_id\":$BID,\"category_id\":$CATID,\"subcategory_id\":$SCATID,\"items\":[{\"item_name\":\"Smoke Dress\",\"quantity\":1,\"unit_price\":500,\"dress_category_id\":$CATID,\"dress_subcategory_id\":$SCATID}]}" "$TT" "$TENANT_SLUG")
POID=$(echo "$PO" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
[ -n "$POID" ] && call POST "/api/tenant/purchase-orders/$POID/receive" "{}" "$TT" "$TENANT_SLUG" >/dev/null
DRES=$(call GET "/api/tenant/dresses?search=Smoke" "" "$TT" "$TENANT_SLUG")
DID=$(echo "$DRES" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[0]['id'] if data else '')" 2>/dev/null || true)
DCODE=$(echo "$DRES" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); data=d.get('data') or []; print(data[0]['code'] if data else '')" 2>/dev/null || true)
if [ -n "$DID" ] && [ -n "$DCODE" ] && [ -n "$CATID" ] && [ -n "$SCATID" ]; then
  call PUT "/api/tenant/dresses/$DID" "{\"code\":\"$DCODE\",\"branch_id\":$BID,\"dress_category_id\":$CATID,\"dress_subcategory_id\":$SCATID,\"sale_price\":1200,\"rental_price\":400,\"status\":\"available\"}" "$TT" "$TENANT_SLUG" >/dev/null

  SALE=$(call POST /api/tenant/sales/invoices "{\"customer_id\":$CID,\"branch_id\":$BID,\"items\":[{\"dress_id\":$DID,\"quantity\":1,\"unit_price\":1200}],\"initial_payment\":{\"amount\":500,\"method\":\"cash\"}}" "$TT" "$TENANT_SLUG")
  SAH=$(echo "$SALE" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
  [ "$SAH" = "201" ] && record tenant "sale invoice" PASS "$SAH" ok || record tenant "sale invoice" FAIL "$SAH" "$(echo "$SALE" | sed '/__HTTP__:/d' | head -c 120)"

  START=$(date -I -d '+40 days')
  END=$(date -I -d '+45 days')
  RENT=$(call POST /api/tenant/invoices "{\"type\":\"rent\",\"status\":\"confirmed\",\"customer_id\":$CID,\"branch_id\":$BID,\"rent_start_date\":\"$START\",\"rent_end_date\":\"$END\",\"delivery_date\":\"$START\",\"return_date\":\"$END\",\"days_of_rent\":6,\"items\":[{\"dress_id\":$DID,\"quantity\":1,\"unit_price\":400}],\"initial_payment\":{\"amount\":200,\"method\":\"cash\"},\"security_deposit\":300}" "$TT" "$TENANT_SLUG")
  RH=$(echo "$RENT" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
  [ "$RH" = "201" ] && record tenant "rental invoice" PASS "$RH" ok || record tenant "rental invoice" FAIL "$RH" "$(echo "$RENT" | sed '/__HTTP__:/d' | head -c 120)"

  INV=$(echo "$SALE" | sed '/__HTTP__:/d' | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('id',''))" 2>/dev/null || true)
  if [ -n "$INV" ]; then
    PAY=$(call POST "/api/tenant/invoices/$INV/payments" "{\"amount\":100,\"method\":\"cash\"}" "$TT" "$TENANT_SLUG")
    PH2=$(echo "$PAY" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
    [ "$PH2" = "200" ] && record tenant "payment create" PASS "$PH2" ok || record tenant "payment create" FAIL "$PH2" "$(echo "$PAY" | sed '/__HTTP__:/d' | head -c 120)"
  else
    record tenant "payment create" FAIL 0 "no invoice id"
  fi
else
  record tenant "sale invoice" FAIL 0 "dress chain incomplete"
  record tenant "rental invoice" FAIL 0 "dress chain incomplete"
  record tenant "payment create" FAIL 0 "dress chain incomplete"
fi
set -e

TODAY=$(date -I)
PDF=$(call GET "/api/tenant/reports/sales-daily?export=pdf&date_from=$TODAY&date_to=$TODAY" "" "$TT" "$TENANT_SLUG")
PDFH=$(echo "$PDF" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
PDFB=$(echo "$PDF" | sed '/__HTTP__:/d' | head -c 20)
[[ "$PDFH" = "200" && "$PDFB" == *PDF* ]] && record tenant "reports PDF" PASS "$PDFH" ok || record tenant "reports PDF" FAIL "$PDFH" "$PDFB"

XLS=$(call GET "/api/tenant/reports/sales-daily?export=xlsx&date_from=$TODAY&date_to=$TODAY" "" "$TT" "$TENANT_SLUG")
XLSH=$(echo "$XLS" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
XLSB=$(echo "$XLS" | sed '/__HTTP__:/d' | head -c 4)
[[ "$XLSH" = "200" && "$XLSB" == PK* ]] && record tenant "reports Excel" PASS "$XLSH" ok || record tenant "reports Excel" FAIL "$XLSH" "$XLSB"

# Mock payment / stack traces
UPG=$(call POST /api/tenant/subscription/upgrade "{\"plan_code\":\"pro\",\"mock_payment_confirmed\":true,\"payment_gateway_id\":1}" "$TT" "$TENANT_SLUG")
UPB=$(echo "$UPG" | sed '/__HTTP__:/d')
UPH=$(echo "$UPG" | grep '__HTTP__:' | tail -1 | sed 's/.*__HTTP__://')
echo "$UPB" | grep -qi 'MOCK-' && record security "no MOCK in upgrade" FAIL "$UPH" "MOCK found" || record security "no MOCK in upgrade" PASS "$UPH" "blocked or no mock"
[ "$(has_stack "$UPB")" = "no" ] && record security "no stack trace upgrade" PASS "$UPH" ok || record security "no stack trace upgrade" FAIL "$UPH" stack

BAD=$(call POST /api/tenant/customers "{\"name\":\"\",\"phone\":\"\"}" "$TT" "$TENANT_SLUG")
BADB=$(echo "$BAD" | sed '/__HTTP__:/d')
[ "$(has_stack "$BADB")" = "no" ] && record security "validation no stack" PASS 422 ok || record security "validation no stack" FAIL 0 stack

# nginx pending
grep -q dressnmore-production /etc/nginx/conf.d/api.conf 2>/dev/null && NG=FAIL || NG=PASS
record infra "nginx not switched" "$NG" 0 "$(grep root /etc/nginx/conf.d/api.conf | head -1)"

pkill -f 'artisan serve.*18099' 2>/dev/null || true
echo SMOKE_DONE
cat "$RESULT"
