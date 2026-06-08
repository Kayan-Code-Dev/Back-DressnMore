#!/usr/bin/env python3
"""
Full System Operational QA — staging API only.
Creates QA-FULL-* entities, runs workflows, writes JSON log. No deletes.
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import traceback
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta
from typing import Any

API_BASE = "https://staging-api.dressnmore.it.com/api/tenant"
TENANT = "phase1-qa"
OWNER_EMAIL = "phase1.qa@dressnmore.test"
OWNER_PASSWORD = "Phase1QA2026!"
PREFIX = "QA-FULL-"
FRONTEND = "https://staging-tenant.dressnmore.it.com"
# Staging WAF blocks Python's default urllib User-Agent (HTTP 403).
QA_HTTP_HEADERS = {
    "User-Agent": "DressnMore-QA/1.0 (full_system_operational_qa)",
    "Accept": "application/json",
}

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")


@dataclass
class StepResult:
    module: str
    scenario: str
    result: str  # PASS | FAIL | PARTIAL | NOT TESTED | SKIP
    http_status: int | None = None
    notes: str = ""
    evidence: str = ""


@dataclass
class Bug:
    bug_id: str
    title: str
    module: str
    severity: str
    steps: str
    expected: str
    actual: str
    evidence: str = ""


@dataclass
class Gap:
    title: str
    module: str
    impact: str
    required: str
    priority: str


@dataclass
class QaState:
    token: str | None = None
    created: list[dict[str, Any]] = field(default_factory=list)
    steps: list[StepResult] = field(default_factory=list)
    bugs: list[Bug] = field(default_factory=list)
    gaps: list[Gap] = field(default_factory=list)
    ids: dict[str, Any] = field(default_factory=dict)
    http_log: list[dict[str, Any]] = field(default_factory=list)
    bug_counter: int = 0


def api(
    state: QaState,
    method: str,
    path: str,
    body: dict | None = None,
    *,
    expect: int | tuple[int, ...] | None = None,
) -> tuple[int, dict[str, Any]]:
    url = f"{API_BASE}{path}"
    headers = {
        **QA_HTTP_HEADERS,
        "Content-Type": "application/json",
        "X-Tenant": TENANT,
    }
    if state.token:
        headers["Authorization"] = f"Bearer {state.token}"
    data = json.dumps(body).encode("utf-8") if body is not None else None
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=45) as resp:
            status = resp.status
            raw = resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        status = e.code
        raw = e.read().decode("utf-8", errors="replace")
    try:
        payload: dict[str, Any] = json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        payload = {"message": raw[:500]}
    state.http_log.append({"method": method, "path": path, "status": status, "success": payload.get("success")})
    if expect is not None:
        ok_codes = (expect,) if isinstance(expect, int) else expect
        if status not in ok_codes:
            state.bugs.append(
                Bug(
                    bug_id=next_bug_id(state),
                    title=f"Unexpected HTTP {status} on {method} {path}",
                    module="API",
                    severity="Medium",
                    steps=f"{method} {path}",
                    expected=f"HTTP {ok_codes}",
                    actual=f"HTTP {status}: {json.dumps(payload)[:300]}",
                    evidence=raw[:400],
                )
            )
    return status, payload


def next_bug_id(state: QaState) -> str:
    state.bug_counter += 1
    return f"QA-BUG-{state.bug_counter:03d}"


def record(
    state: QaState,
    module: str,
    scenario: str,
    status: int,
    payload: dict[str, Any],
    *,
    pass_on_success: bool = True,
    notes: str = "",
) -> None:
    ok = payload.get("success", status < 400)
    if pass_on_success and ok:
        result = "PASS"
    elif ok:
        result = "PARTIAL"
    else:
        result = "FAIL"
    evidence = json.dumps(payload, ensure_ascii=False)[:350]
    state.steps.append(
        StepResult(
            module=module,
            scenario=scenario,
            result=result,
            http_status=status,
            notes=notes,
            evidence=evidence,
        )
    )


def track_created(state: QaState, kind: str, name: str, obj_id: Any, purpose: str, status: str = "created"):
    state.created.append(
        {"type": kind, "name": name, "id": obj_id, "purpose": purpose, "status": status}
    )


HR_STAFF_PASSWORD = "QAFullSales2026!"
QA_ARTISAN_CWD = os.environ.get("QA_ARTISAN_CWD", "/var/www/back-dressnmore-new")


def register_email_in_tenant_directory(state: QaState, email: str) -> bool:
    """Register login email in central directory (required for POST /login; HR create omits this)."""
    if not os.path.isdir(QA_ARTISAN_CWD):
        record(
            state,
            "HR",
            "Directory register (skipped)",
            0,
            {"success": False, "message": f"QA_ARTISAN_CWD missing: {QA_ARTISAN_CWD}"},
            pass_on_success=False,
            notes="Run on staging server for login provisioning",
        )
        return False
    safe_email = email.replace("'", "\\'")
    php = (
        f"$t=\\App\\Models\\Central\\Tenant::where('slug','{TENANT}')->first();"
        f"app(\\App\\Services\\Tenant\\TenantUserDirectoryService::class)->register($t,'{safe_email}');"
        "echo 'ok';"
    )
    try:
        proc = subprocess.run(
            ["php", "artisan", "tinker", f"--execute={php}"],
            cwd=QA_ARTISAN_CWD,
            capture_output=True,
            text=True,
            timeout=45,
            check=False,
        )
        ok = proc.returncode == 0 and "ok" in (proc.stdout or "")
        record(
            state,
            "HR",
            "Directory register for HR login",
            200 if ok else 500,
            {"success": ok, "stdout": (proc.stdout or "")[:200], "stderr": (proc.stderr or "")[:200]},
            notes=email,
        )
        return ok
    except OSError as exc:
        record(
            state,
            "HR",
            "Directory register for HR login",
            500,
            {"success": False, "message": str(exc)},
            pass_on_success=False,
        )
        return False


def pick_hr_staff_role_id(state: QaState) -> int | None:
    """GET /hr/access/roles and return first non-owner role (prefer staff-like slugs)."""
    status, data = api(state, "GET", "/hr/access/roles")
    record(state, "HR", "GET /hr/access/roles", status, data)
    if not data.get("success"):
        return None
    roles: list[dict[str, Any]] = list(data.get("data") or [])
    preferred_slugs = ("staff", "cashier", "hr-test-staff", "tenant-staff", "sales")
    for slug in preferred_slugs:
        for role in roles:
            if role.get("slug") == slug and role.get("id"):
                return int(role["id"])
    for role in roles:
        if role.get("slug") != "owner" and role.get("id"):
            return int(role["id"])
    return None


def build_hr_employee_body(
    state: QaState,
    *,
    role_key: str,
    full_name: str,
    employee_code: str,
    login_email: str,
    role_id: int,
) -> dict[str, Any]:
    branch_main = state.ids.get("branch_main")
    body: dict[str, Any] = {
        "employee_code": employee_code,
        "full_name": full_name,
        "phone": "0500777666",
        "email": login_email,
        "employment_type": "full_time",
        "status": "active",
        "joining_date": date.today().isoformat(),
        "base_salary": 5000,
        "salary_type": "monthly",
        "user_account": {
            "email": login_email,
            "password": HR_STAFF_PASSWORD,
            "password_confirmation": HR_STAFF_PASSWORD,
            "role_id": role_id,
        },
    }
    if branch_main:
        body["branch_id"] = branch_main
    return body


def create_hr_employee_with_login(
    state: QaState,
    *,
    role_key: str = "sales",
    full_name: str | None = None,
    employee_code: str | None = None,
    login_email: str | None = None,
) -> bool:
    role_id = pick_hr_staff_role_id(state)
    if role_id is None:
        state.gaps.append(
            Gap(
                title="HR access roles unavailable",
                module="HR",
                impact="Cannot assign role_id for employee login",
                required="GET /hr/access/roles returns non-owner roles",
                priority="P1",
            )
        )
        return False

    state.ids["hr_role_id_used"] = role_id
    run_tag = datetime.now().strftime("%Y%m%d%H%M%S")
    code = employee_code or f"{PREFIX}EMP-{role_key.upper()}-{run_tag}"
    name = full_name or f"{PREFIX}Employee-{role_key.title()}"
    email = login_email or f"qa-full-{role_key}-{run_tag}@{TENANT}.dressnmore.test"

    status, hr_emp = api(
        state,
        "POST",
        "/hr/employees",
        build_hr_employee_body(
            state,
            role_key=role_key,
            full_name=name,
            employee_code=code,
            login_email=email,
            role_id=role_id,
        ),
        expect=201,
    )
    if hr_emp.get("success"):
        data = hr_emp.get("data") or {}
        state.ids[f"hr_{role_key}"] = data.get("id")
        state.ids["hr_employee_id"] = data.get("id")
        state.ids["hr_user_id"] = data.get("user_id")
        state.ids["hr_sales_email"] = email
        state.ids["hr_sales_password"] = HR_STAFF_PASSWORD
        track_created(state, "hr_employee", name, data.get("id"), f"HR login QA {role_key}")
        record(
            state,
            "HR",
            "POST /hr/employees with user_account",
            status,
            hr_emp,
            notes=f"employee_id={data.get('id')} user_id={data.get('user_id')} role_id={role_id}",
        )
        if data.get("user_id"):
            register_email_in_tenant_directory(state, email)
        return bool(data.get("user_id"))
    record(
        state,
        "HR",
        "POST /hr/employees with user_account",
        status,
        hr_emp,
        pass_on_success=False,
        notes=hr_emp.get("message", ""),
    )
    return False


def login_owner(state: QaState) -> bool:
    status, data = api(
        state,
        "POST",
        "/login",
        {
            "workspace": TENANT,
            "email": OWNER_EMAIL,
            "password": OWNER_PASSWORD,
        },
        expect=200,
    )
    if data.get("success") and data.get("data", {}).get("token"):
        state.token = data["data"]["token"]
        state.ids["owner_user_id"] = data["data"].get("user", {}).get("id")
        state.ids["permissions"] = data["data"].get("permissions", [])
        record(state, "Auth", "Owner login", status, data)
        return True
    record(state, "Auth", "Owner login", status, data, pass_on_success=False)
    return False


def setup_test_data(state: QaState) -> None:
    """Create QA-FULL-* master data."""
    # Branches
    for code, name in [("MAIN", "Main Branch"), ("SEC", "Second Branch")]:
        status, data = api(
            state,
            "POST",
            "/branches",
            {
                "name": f"{PREFIX}Branch-{name}",
                "code": f"{PREFIX}{code}",
                "phone": "0500111222",
                "address": f"{PREFIX}address {code}",
                "status": "active",
            },
        )
        if data.get("success"):
            bid = data["data"]["id"]
            key = "branch_main" if code == "MAIN" else "branch_sec"
            state.ids[key] = bid
            track_created(state, "branch", f"{PREFIX}Branch-{name}", bid, "branch testing")
        record(state, "Branches", f"Create branch {code}", status, data, notes=data.get("message", ""))

    branch_main = state.ids.get("branch_main")

    # Categories + subcategory
    status, cat = api(
        state,
        "POST",
        "/dress-categories",
        {"name": f"{PREFIX}Category-SaleRent", "status": "active"},
    )
    cat_id = cat.get("data", {}).get("id") if cat.get("success") else None
    if cat_id:
        state.ids["category_id"] = cat_id
        track_created(state, "category", f"{PREFIX}Category-SaleRent", cat_id, "catalog")
    record(state, "Catalog", "Create dress category", status, cat)

    sub_id = None
    if cat_id:
        status, sub = api(
            state,
            "POST",
            f"/dress-categories/{cat_id}/subcategories",
            {"name": f"{PREFIX}Subcategory-A", "status": "active"},
        )
        sub_id = sub.get("data", {}).get("id") if sub.get("success") else None
        if sub_id:
            state.ids["subcategory_id"] = sub_id
            track_created(state, "subcategory", f"{PREFIX}Subcategory-A", sub_id, "catalog")
        record(state, "Catalog", "Create subcategory", status, sub)

    # Dresses — sale + rent
    if cat_id and sub_id:
        for dtype, code_suffix, sale_p, rent_p in [
            ("sell", "Sale", 2500, None),
            ("rent", "Rent", None, 800),
        ]:
            body: dict[str, Any] = {
                "code": f"{PREFIX}Dress-{code_suffix}",
                "name": f"{PREFIX}Dress-{code_suffix}",
                "dress_category_id": cat_id,
                "dress_subcategory_id": sub_id,
                "status": "available",
                "branch_id": branch_main,
            }
            if sale_p:
                body["sale_price"] = sale_p
            if rent_p:
                body["rental_price"] = rent_p
            status, dress = api(state, "POST", "/dresses", body)
            if dress.get("success"):
                did = dress["data"]["id"]
                state.ids[f"dress_{code_suffix.lower()}"] = did
                track_created(state, "dress", body["name"], did, dtype)
            record(state, "Catalog", f"Create dress {code_suffix}", status, dress)

    # Customer
    status, cust = api(
        state,
        "POST",
        "/customers",
        {
            "name": f"{PREFIX}Customer-A",
            "phone": "0500999888",
            "source": "walk_in",
            "status": "active",
            "notes": f"{PREFIX}test customer",
        },
    )
    if cust.get("success"):
        state.ids["customer_id"] = cust["data"]["id"]
        track_created(state, "customer", f"{PREFIX}Customer-A", cust["data"]["id"], "invoices")
    record(state, "Customers", "Create customer", status, cust)

    # Supplier
    status, sup = api(
        state,
        "POST",
        "/suppliers",
        {
            "name": f"{PREFIX}Supplier-A",
            "phone": "0500888777",
            "status": "active",
        },
    )
    if sup.get("success"):
        state.ids["supplier_id"] = sup["data"]["id"]
        track_created(state, "supplier", f"{PREFIX}Supplier-A", sup["data"]["id"], "purchases")
    record(state, "Suppliers", "Create supplier", status, sup)

    # Cashbox
    status, cb = api(
        state,
        "POST",
        "/cashboxes",
        {
            "name": f"{PREFIX}Cashbox-Main",
            "branch_id": branch_main,
            "opening_balance": 1000,
            "status": "active",
        },
    )
    if cb.get("success"):
        state.ids["cashbox_id"] = cb["data"]["id"]
        track_created(state, "cashbox", f"{PREFIX}Cashbox-Main", cb["data"]["id"], "payments")
    record(state, "Cashbox", "Create cashbox", status, cb)

    # Expense category + expense
    status, exp_cat = api(state, "GET", "/expense-categories?per_page=5")
    record(state, "Expenses", "List expense categories", status, exp_cat)
    exp_cat_id = None
    if exp_cat.get("data"):
        exp_cat_id = exp_cat["data"][0].get("id")
    status, exp = api(
        state,
        "POST",
        "/expenses",
        {
            "title": f"{PREFIX}Expense-Office",
            "amount": 150,
            "expense_date": date.today().isoformat(),
            "branch_id": branch_main,
            "expense_category_id": exp_cat_id,
            "payment_method": "cash",
            "status": "approved",
        },
    )
    if exp.get("success"):
        track_created(state, "expense", f"{PREFIX}Expense-Office", exp["data"].get("id"), "accounting")
    record(state, "Expenses", "Create expense", status, exp)

    # HR employee + tenant user login (correct user_account contract)
    create_hr_employee_with_login(state, role_key="sales")


def test_sales_workflow(state: QaState) -> None:
    cid = state.ids.get("customer_id")
    dress_id = state.ids.get("dress_sale")
    if not cid or not dress_id:
        state.steps.append(StepResult("Sales", "Full sale workflow", "NOT TESTED", notes="Missing customer/dress"))
        return

    status, sale = api(
        state,
        "POST",
        "/sales/invoices",
        {
            "customer_id": cid,
            "branch_id": state.ids.get("branch_main"),
            "items": [
                {
                    "dress_id": dress_id,
                    "description": f"{PREFIX}sale line",
                    "quantity": 1,
                    "unit_price": 2000,
                }
            ],
            "discount": 100,
            "tax": 50,
            "initial_payment": {"amount": 500, "method": "cash"},
        },
    )
    sale_id = sale.get("data", {}).get("id") if sale.get("success") else None
    if sale_id:
        state.ids["sale_invoice_id"] = sale_id
        track_created(state, "sale_invoice", f"{PREFIX}Sale-1", sale_id, "sales workflow")
    record(state, "Sales", "Create sale with discount/tax/partial payment", status, sale)

    if sale_id:
        status, show = api(state, "GET", f"/sales/invoices/{sale_id}")
        record(state, "Sales", "Get sale invoice detail", status, show)

        status, pay = api(
            state,
            "POST",
            f"/invoices/{sale_id}/payments",
            {"amount": 500, "method": "cash", "notes": f"{PREFIX}second payment"},
        )
        record(state, "Sales", "Add second payment", status, pay)

        status, cancel = api(state, "POST", f"/invoices/{sale_id}/cancel")
        record(
            state,
            "Sales",
            "Cancel sale invoice (no delete)",
            status,
            cancel,
            notes="Expected cancel supported without delete",
        )


def test_rental_workflow(state: QaState) -> None:
    cid = state.ids.get("customer_id")
    dress_rent = state.ids.get("dress_rent")
    if not cid or not dress_rent:
        state.steps.append(StepResult("Rental", "Full rental workflow", "NOT TESTED", notes="Missing data"))
        return

    start = (date.today() + timedelta(days=20)).isoformat()
    end = (date.today() + timedelta(days=25)).isoformat()
    overlap_start = (date.today() + timedelta(days=22)).isoformat()
    overlap_end = (date.today() + timedelta(days=27)).isoformat()
    free_start = (date.today() + timedelta(days=60)).isoformat()
    free_end = (date.today() + timedelta(days=63)).isoformat()

    # Availability endpoints
    status, avail = api(state, "GET", f"/dresses/{dress_rent}/unavailable-days?from={start}&to={end}")
    record(state, "Rental", "Unavailable days lookup", status, avail)

    status, rent1 = api(
        state,
        "POST",
        "/invoices",
        {
            "type": "rent",
            "status": "confirmed",
            "customer_id": cid,
            "branch_id": state.ids.get("branch_main"),
            "rent_start_date": start,
            "rent_end_date": end,
            "delivery_date": start,
            "return_date": end,
            "occasion_datetime": start,
            "days_of_rent": 6,
            "items": [{"dress_id": dress_rent, "quantity": 1, "unit_price": 800}],
            "initial_payment": {"amount": 200, "method": "cash"},
            "security_deposit": 300,
            "notes": f"{PREFIX}rental-1",
        },
    )
    rent_id = rent1.get("data", {}).get("id") if rent1.get("success") else None
    if rent_id:
        state.ids["rent_invoice_id"] = rent_id
        track_created(state, "rent_invoice", f"{PREFIX}Rent-1", rent_id, "rental")
    record(state, "Rental", "Create confirmed rental", status, rent1)

    # Conflict test — overlapping dates
    status, conflict = api(
        state,
        "POST",
        "/invoices",
        {
            "type": "rent",
            "status": "confirmed",
            "customer_id": cid,
            "rent_start_date": overlap_start,
            "rent_end_date": overlap_end,
            "delivery_date": overlap_start,
            "return_date": overlap_end,
            "items": [{"dress_id": dress_rent, "quantity": 1, "unit_price": 800}],
        },
    )
    if conflict.get("success"):
        state.bugs.append(
            Bug(
                bug_id=next_bug_id(state),
                title="Rental double-booking allowed on overlapping dates",
                module="Rental",
                severity="Critical",
                steps=f"Book dress {dress_rent} {start}-{end}, then {overlap_start}-{overlap_end}",
                expected="Second booking rejected (422)",
                actual="Second booking succeeded",
                evidence=json.dumps(conflict)[:400],
            )
        )
        record(state, "Rental", "Conflict: overlapping booking blocked", status, conflict, pass_on_success=False)
    else:
        record(state, "Rental", "Conflict: overlapping booking blocked", status, conflict, notes="Rejected as expected")

    # Free dates should work (different dress or same dress far future)
    status, free = api(
        state,
        "POST",
        "/invoices",
        {
            "type": "rent",
            "status": "confirmed",
            "customer_id": cid,
            "rent_start_date": free_start,
            "rent_end_date": free_end,
            "delivery_date": free_start,
            "return_date": free_end,
            "items": [{"dress_id": dress_rent, "quantity": 1, "unit_price": 800}],
            "notes": f"{PREFIX}rental-free-dates",
        },
    )
    record(state, "Rental", "Non-overlapping future booking", status, free)

    if rent_id:
        status, delivery = api(state, "POST", f"/deliveries/rental/{rent_id}/deliver", {})
        record(state, "Rental", "Deliver rental dress", status, delivery, notes=delivery.get("message", ""))

        status, ret = api(
            state,
            "POST",
            f"/deliveries/rental/{rent_id}/return",
            {
                "return_date": end,
                "late_fee": 50,
                "deductions": [{"reason": f"{PREFIX}minor damage", "amount": 25}],
            },
        )
        record(state, "Rental", "Return rental with late fee/deduction", status, ret, notes=ret.get("message", ""))


def test_tailoring_workflow(state: QaState) -> None:
    cid = state.ids.get("customer_id")
    if not cid:
        state.steps.append(StepResult("Tailoring", "Full workflow", "NOT TESTED", notes="No customer"))
        return
    status, order = api(
        state,
        "POST",
        "/tailoring/orders",
        {
            "customer_id": cid,
            "branch_id": state.ids.get("branch_main"),
            "occasion_date": (date.today() + timedelta(days=30)).isoformat(),
            "delivery_date": (date.today() + timedelta(days=25)).isoformat(),
            "notes": f"{PREFIX}tailoring order",
            "items": [
                {
                    "description": f"{PREFIX}custom abaya",
                    "quantity": 1,
                    "unit_price": 1200,
                }
            ],
            "measurements": {"chest": 90, "waist": 70, "height": 165},
        },
    )
    order_id = order.get("data", {}).get("id") if order.get("success") else None
    if order_id:
        state.ids["tailoring_order_id"] = order_id
        track_created(state, "tailoring_order", f"{PREFIX}Tailoring-1", order_id, "tailoring")
    record(state, "Tailoring", "Create tailoring order", status, order)

    if order_id:
        status, show = api(state, "GET", f"/tailoring/orders/{order_id}")
        record(state, "Tailoring", "Get tailoring order", status, show)

        status, status_up = api(
            state,
            "PATCH",
            f"/tailoring/orders/{order_id}/status",
            {"status": "in_progress"},
        )
        record(state, "Tailoring", "Update tailoring status", status, status_up, notes=status_up.get("message", ""))


def test_purchases(state: QaState) -> None:
    sid = state.ids.get("supplier_id")
    if not sid:
        state.steps.append(StepResult("Purchases", "PO workflow", "NOT TESTED", notes="No supplier"))
        return
    status, po = api(
        state,
        "POST",
        "/purchase-orders",
        {
            "supplier_id": sid,
            "branch_id": state.ids.get("branch_main"),
            "notes": f"{PREFIX}PO-1",
            "items": [
                {
                    "description": f"{PREFIX}fabric roll",
                    "quantity": 2,
                    "unit_cost": 300,
                }
            ],
        },
    )
    po_id = po.get("data", {}).get("id") if po.get("success") else None
    if po_id:
        track_created(state, "purchase_order", f"{PREFIX}PO-1", po_id, "suppliers")
    record(state, "Purchases", "Create purchase order", status, po)

    if po_id:
        status, pay = api(
            state,
            "POST",
            f"/purchase-orders/{po_id}/payments",
            {"amount": 300, "method": "cash"},
        )
        record(state, "Purchases", "Supplier PO payment", status, pay, notes=pay.get("message", ""))


def test_reports_dashboard(state: QaState) -> None:
    endpoints = [
        ("Dashboard", "GET", "/dashboard"),
        ("Dashboard", "GET", "/dashboard/summary"),
        ("Reports", "GET", "/reports/sales/summary"),
        ("Reports", "GET", "/reports/payments/summary"),
        ("Reports", "GET", "/reports/expenses/summary"),
        ("Accounting", "GET", "/accounting/summary"),
        ("HR", "GET", "/hr/dashboard"),
    ]
    for module, method, path in endpoints:
        status, data = api(state, method, path)
        record(state, module, f"Fetch {path}", status, data, notes="" if data.get("success") else data.get("message", ""))


def test_permissions_employee_login(state: QaState) -> None:
    email = state.ids.get("hr_sales_email")
    password = state.ids.get("hr_sales_password", HR_STAFF_PASSWORD)
    if not email:
        state.steps.append(
            StepResult("Permissions", "Employee login + API guard", "NOT TESTED", notes="No HR employee login")
        )
        return

    emp_state = QaState()
    status, data = api(
        emp_state,
        "POST",
        "/login",
        {"workspace": TENANT, "email": email, "password": password},
        expect=200,
    )
    if not data.get("success"):
        record(state, "Permissions", "POST /login (HR staff)", status, data, pass_on_success=False)
        return
    emp_state.token = data["data"]["token"]
    emp_perms = list(data["data"].get("permissions", []))
    state.ids["hr_staff_permissions"] = emp_perms
    record(
        state,
        "Permissions",
        "POST /login (HR staff)",
        status,
        data,
        notes=f"perms_count={len(emp_perms)} sample={emp_perms[:6]}",
    )

    owner_token = state.token
    state.token = emp_state.token

    def has_perm(key: str) -> bool:
        return key in emp_perms

    sale_body: dict[str, Any] | None = None
    if state.ids.get("customer_id") and state.ids.get("branch_main"):
        sale_body = {
            "customer_id": state.ids["customer_id"],
            "branch_id": state.ids["branch_main"],
            "items": [{"description": f"{PREFIX}staff-sale", "quantity": 1, "unit_price": 100}],
        }

    permission_checks: list[tuple[str, str, str, dict | None, str, str | None]] = [
        ("GET /dashboard/overview (staff)", "GET", "/dashboard/overview", None, "perm_gate", "dashboard.view"),
        ("GET /hr/dashboard (staff)", "GET", "/hr/dashboard", None, "perm_gate", "hr.dashboard.view"),
        ("GET /hr/settings (staff)", "GET", "/hr/settings", None, "perm_gate", "hr.settings.view"),
        (
            "DELETE /hr/employees/{id}",
            "DELETE",
            f"/hr/employees/{state.ids.get('hr_employee_id', 0)}",
            None,
            "perm_gate",
            "hr.employees.delete",
        ),
        ("POST /sales/invoices (staff)", "POST", "/sales/invoices", sale_body, "perm_gate", "invoices.create"),
    ]

    for label, method, path, body, mode, required_perm in permission_checks:
        if body is None and method == "POST":
            state.steps.append(
                StepResult(
                    "Permissions",
                    label,
                    "SKIP",
                    notes="missing customer_id/branch_id for payload",
                )
            )
            continue
        status, payload = api(state, method, path, body)
        ok_success = bool(payload.get("success"))
        if mode == "perm_gate" and required_perm:
            if has_perm(required_perm):
                passed = ok_success and status in (200, 201)
                note = f"has {required_perm} → HTTP {status}"
            else:
                passed = status in (401, 403) or not ok_success
                note = f"no {required_perm} → blocked HTTP {status}"
        elif mode == "expect_block":
            passed = status in (401, 403) or not ok_success
            note = f"blocked HTTP {status}"
        else:
            passed = ok_success and status == 200
            note = f"HTTP {status}"
        state.steps.append(
            StepResult(
                "Permissions",
                label,
                "PASS" if passed else "FAIL",
                http_status=status,
                notes=note,
                evidence=json.dumps(payload, ensure_ascii=False)[:300],
            )
        )

    state.token = owner_token
    state.steps.append(
        StepResult(
            "Permissions",
            "UI pages (manual)",
            "NOT TESTED",
            notes="API-only run; verify sidebar routes in browser for staff user",
        )
    )


def test_edge_cases(state: QaState) -> None:
    cid = state.ids.get("customer_id")
    dress = state.ids.get("dress_rent")

    status, no_items = api(state, "POST", "/sales/invoices", {"customer_id": cid, "items": []})
    record(
        state,
        "Edge Cases",
        "Sale without items",
        status,
        no_items,
        notes="expect validation failure",
        pass_on_success=not no_items.get("success"),
    )

    status, overpay = api(
        state,
        "POST",
        f"/invoices/{state.ids.get('sale_invoice_id', 0)}/payments",
        {"amount": 999999, "method": "cash"},
    )
    record(state, "Edge Cases", "Overpayment on invoice", status, overpay, notes=overpay.get("message", ""))

    if dress:
        bad_dates = api(
            state,
            "POST",
            "/invoices",
            {
                "type": "rent",
                "status": "confirmed",
                "customer_id": cid,
                "rent_start_date": (date.today() + timedelta(days=5)).isoformat(),
                "rent_end_date": (date.today() + timedelta(days=2)).isoformat(),
                "delivery_date": (date.today() + timedelta(days=5)).isoformat(),
                "return_date": (date.today() + timedelta(days=2)).isoformat(),
                "items": [{"dress_id": dress, "quantity": 1, "unit_price": 100}],
            },
        )
        status, payload = bad_dates
        record(
            state,
            "Edge Cases",
            "Return date before delivery",
            status,
            payload,
            pass_on_success=not payload.get("success"),
        )


def run_hr_login_qa_only() -> int:
    """Minimal owner setup + HR employee login contract verification."""
    state = QaState()
    print(f"=== HR Login QA — {datetime.now().isoformat()} ===")
    if not login_owner(state):
        last = state.http_log[-1] if state.http_log else {}
        auth_step = next((s for s in state.steps if s.scenario == "Owner login"), None)
        print("BLOCKER: owner login failed", f"http={last.get('status')}", auth_step.evidence if auth_step else "")
        return 1
    branch_main = state.ids.get("branch_main")
    if not branch_main:
        status, data = api(
            state,
            "POST",
            "/branches",
            {
                "name": f"{PREFIX}Branch-Main",
                "code": f"{PREFIX}MAIN",
                "phone": "0500111222",
                "address": f"{PREFIX}address",
                "status": "active",
            },
        )
        if data.get("success"):
            state.ids["branch_main"] = data["data"]["id"]
    if not state.ids.get("customer_id") and state.ids.get("branch_main"):
        status, data = api(
            state,
            "POST",
            "/customers",
            {
                "name": f"{PREFIX}Customer-Perm",
                "phone": "0500333444",
                "source": "walk_in",
                "status": "active",
            },
        )
        if data.get("success"):
            state.ids["customer_id"] = data["data"]["id"]
    create_hr_employee_with_login(state, role_key="sales")
    test_permissions_employee_login(state)
    auth_errors = [
        e
        for e in state.http_log
        if e.get("status") in (401, 403, 422)
    ]
    perm_steps = [s for s in state.steps if s.module == "Permissions"]
    login_step = next((s for s in perm_steps if "login" in s.scenario.lower()), None)
    report = {
        "role_id": state.ids.get("hr_role_id_used"),
        "employee_id": state.ids.get("hr_employee_id"),
        "user_id": state.ids.get("hr_user_id"),
        "login_email": state.ids.get("hr_sales_email"),
        "login_result": login_step.result if login_step else "NOT RUN",
        "login_http": login_step.http_status if login_step else None,
        "permissions_tested": [s.scenario for s in perm_steps],
        "permissions_sample": state.ids.get("hr_staff_permissions", [])[:20],
        "page_access": {
            s.scenario: {"result": s.result, "http": s.http_status, "notes": s.notes}
            for s in perm_steps
            if s.scenario != "UI pages (manual)" and "login" not in s.scenario.lower()
        },
        "auth_errors_401_403_422": auth_errors,
        "steps": [s.__dict__ for s in state.steps if s.module in ("HR", "Permissions", "Auth")],
    }
    print("__HR_QA_REPORT__", json.dumps(report, ensure_ascii=False))
    out_path = "docs/hr-login-qa-results.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(report, f, ensure_ascii=False, indent=2)
    ok = bool(state.ids.get("hr_user_id")) and any(
        s.scenario == "POST /login (HR staff)" and s.result == "PASS" for s in state.steps
    )
    return 0 if ok else 1


def main() -> int:
    if len(sys.argv) > 1 and sys.argv[1] == "--hr-login-only":
        return run_hr_login_qa_only()

    state = QaState()
    print(f"=== Full System QA — {datetime.now().isoformat()} ===")
    print(f"API: {API_BASE}  Tenant: {TENANT}")

    if not login_owner(state):
        print("BLOCKER: cannot login as owner")
        out_path = "docs/full-system-operational-qa-results.json"
        with open(out_path, "w", encoding="utf-8") as f:
            json.dump(
                {
                    "blocked": True,
                    "steps": [s.__dict__ for s in state.steps],
                    "http_log": state.http_log,
                },
                f,
                ensure_ascii=False,
                indent=2,
            )
        return 1

    setup_test_data(state)
    test_sales_workflow(state)
    test_rental_workflow(state)
    test_tailoring_workflow(state)
    test_purchases(state)
    test_reports_dashboard(state)
    test_permissions_employee_login(state)
    test_edge_cases(state)

    out_path = "docs/full-system-operational-qa-results.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(
            {
                "environment": {
                    "frontend": FRONTEND,
                    "api": API_BASE,
                    "tenant": TENANT,
                    "owner_email": OWNER_EMAIL,
                    "run_at": datetime.now().isoformat(),
                },
                "created": state.created,
                "steps": [s.__dict__ for s in state.steps],
                "bugs": [b.__dict__ for b in state.bugs],
                "gaps": [g.__dict__ for g in state.gaps],
                "ids": {k: v for k, v in state.ids.items() if k != "permissions"},
                "http_errors": [h for h in state.http_log if h.get("status", 0) >= 400],
            },
            f,
            ensure_ascii=False,
            indent=2,
        )
    print(f"\nWrote {out_path}")
    passes = sum(1 for s in state.steps if s.result == "PASS")
    fails = sum(1 for s in state.steps if s.result == "FAIL")
    print(f"Steps: {len(state.steps)} PASS={passes} FAIL={fails} bugs={len(state.bugs)}")
    return 0 if fails == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
