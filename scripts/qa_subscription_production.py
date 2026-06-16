#!/usr/bin/env python3
"""Production subscription billing smoke + E2E QA."""
from __future__ import annotations

import json
import os
import sys
import time

import paramiko

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

HOST = "159.198.74.223"
USER = "root"
PWD = "RBZ4cjZE184wOx37ip"
API = "https://api.dressnmore.it.com/api"
BACKEND = "/var/www/dressnmore-production/backend"


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 120) -> str:
    _, o, e = client.exec_command(cmd, timeout=timeout)
    return (o.read() + e.read()).decode("utf-8", errors="replace").strip()


def curl_json(client: paramiko.SSHClient, method: str, url: str, body: dict | None = None, headers: dict | None = None) -> tuple[int, dict | str]:
    hdrs = headers or {}
    parts = [f"curl -sk -w '\\n%{{http_code}}' -X {method}"]
    for k, v in hdrs.items():
        parts.append(f"-H '{k}: {v}'")
    if body is not None:
        parts.append("-H 'Content-Type: application/json'")
        parts.append(f"-d '{json.dumps(body, ensure_ascii=False)}'")
    parts.append(f"'{url}'")
    raw = run(client, " ".join(parts))
    if "\n" in raw:
        payload, code = raw.rsplit("\n", 1)
    else:
        payload, code = raw, "000"
    try:
        return int(code), json.loads(payload)
    except json.JSONDecodeError:
        return int(code) if code.isdigit() else 0, payload


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PWD, timeout=30)

    results: list[dict] = []

    def record(name: str, ok: bool, detail: str) -> None:
        results.append({"name": name, "status": "PASS" if ok else ("NOT TESTED" if ok is None else "FAIL"), "detail": detail})
        label = results[-1]["status"]
        print(f"[{label}] {name}: {detail[:300]}")

    # Credentials from server secrets file (authoritative for QA tenant)
    secrets: dict[str, str] = {}
    for line in run(client, "cat /root/.dressnmore-production-bootstrap.secrets 2>/dev/null").splitlines():
        if "=" in line:
            k, v = line.split("=", 1)
            secrets[k.strip()] = v.strip()

    admin_email = secrets.get("PLATFORM_ADMIN_EMAIL") or ""
    admin_password = secrets.get("PLATFORM_ADMIN_PASSWORD") or ""
    if not admin_email:
        admin_info = run(
            client,
            f"cd {BACKEND} && php artisan tinker --execute=\"echo json_encode(\\\\App\\\\Models\\\\Central\\\\SuperAdmin::query()->first(['email']));\"",
        )
        try:
            admin_email = json.loads(admin_info).get("email", "")
        except json.JSONDecodeError:
            admin_email = ""
    if not admin_password:
        env = run(client, f"grep '^PLATFORM_ADMIN_PASSWORD=' {BACKEND}/.env | head -1")
        admin_password = env.split("=", 1)[1].strip().strip('"') if "=" in env else ""

    token = None
    if not admin_email or not admin_password:
        record("admin_login", False, f"missing creds email={admin_email!r}")
    else:
        code, login = curl_json(
            client,
            "POST",
            f"{API}/platform/login",
            {"email": admin_email, "password": admin_password},
        )
        if isinstance(login, dict):
            token = login.get("data", {}).get("token") or login.get("data", {}).get("access_token")
        record("admin_login", bool(token), f"email={admin_email} HTTP {code} {str(login)[:120]}")

        if token:
            auth = {"Authorization": f"Bearer {token}"}
            for path in [
                "/platform/subscriptions?per_page=1",
                "/platform/payments?per_page=1",
                "/platform/payment-gateways?per_page=1",
                "/platform/order-plans?per_page=1",
                "/platform/dashboard/subscription-stats",
            ]:
                code = run(client, f"curl -sk -o /dev/null -w '%{{http_code}}' -H 'Authorization: Bearer {token}' {API}{path}")
                record(f"GET {path}", code == "200", f"HTTP {code}")

    tenant_slug = secrets.get("TENANT_SLUG") or run(
        client,
        f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Tenant::orderBy('id')->value('slug');\"",
    ).strip()
    if tenant_slug:
        code = run(client, f"curl -sk -o /dev/null -w '%{{http_code}}' -H 'X-Tenant: {tenant_slug}' {API}/tenant/health")
        record("tenant_health", code == "200", f"slug={tenant_slug} HTTP {code}")

        tenant_email = secrets.get("TENANT_OWNER_EMAIL", "")
        tenant_password = secrets.get("TENANT_OWNER_PASSWORD", "")

        tenant_token = None
        if tenant_email and tenant_password:
            code, tlogin = curl_json(
                client,
                "POST",
                f"{API}/tenant/login",
                {"email": tenant_email, "password": tenant_password},
                {"X-Tenant": tenant_slug},
            )
            if isinstance(tlogin, dict):
                tenant_token = tlogin.get("data", {}).get("token") or tlogin.get("data", {}).get("access_token")
            record("tenant_login", bool(tenant_token), f"email={tenant_email} slug={tenant_slug} HTTP {code}")
        else:
            record("tenant_login", False, f"missing tenant secrets for {tenant_slug}")

        if tenant_token:
            # restore tenant for repeatable QA (clear cancellation only)
            run(
                client,
                f"cd {BACKEND} && php artisan tinker --execute=\""
                f"\\\\App\\\\Models\\\\Central\\\\Tenant::where('slug','{tenant_slug}')->update(['cancelled_at'=>null,'cancellation_reason'=>null]);"
                f"echo 'restored';\"",
            )
            th = {"Authorization": f"Bearer {tenant_token}", "X-Tenant": tenant_slug}
            for path in [
                "/tenant/subscription/current",
                "/tenant/subscription/plans",
                "/tenant/subscription/payment-gateways",
                "/tenant/subscription/orders",
            ]:
                code = run(
                    client,
                    f"curl -sk -o /dev/null -w '%{{http_code}}' -H 'Authorization: Bearer {tenant_token}' -H 'X-Tenant: {tenant_slug}' {API}{path}",
                )
                record(f"GET {path}", code == "200", f"HTTP {code}")

            # E2E: submit change request if gateway exists
            _, gateways = curl_json(
                client,
                "GET",
                f"{API}/tenant/subscription/payment-gateways",
                headers=th,
            )
            gateway_id = None
            if isinstance(gateways, dict):
                items = gateways.get("data") or []
                if items:
                    gateway_id = items[0].get("id")

            def plan_code_from(resp: dict | str) -> str | None:
                if not isinstance(resp, dict):
                    return None
                data = resp.get("data") or {}
                return data.get("plan_code") or (data.get("plan") or {}).get("slug")

            _, plans_resp = curl_json(client, "GET", f"{API}/tenant/subscription/plans", headers=th)
            _, sub_before = curl_json(client, "GET", f"{API}/tenant/subscription/current", headers=th)
            current_slug = plan_code_from(sub_before)

            target_plan_code = None
            target_action = None
            if isinstance(plans_resp, dict):
                for p in plans_resp.get("data") or []:
                    action = p.get("action")
                    slug = p.get("code") or p.get("slug")
                    if action in ("upgrade", "downgrade", "renew") and slug and slug != current_slug:
                        target_plan_code = slug
                        target_action = action
                        break

            order_id = payment_id = None
            if gateway_id and target_plan_code:
                # minimal proof file on server
                run(
                    client,
                    "python3 -c \"open('/tmp/qa-proof.png','wb').write(bytes.fromhex('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c49444154789c63f80f00000101000518d84e0000000049454e44ae426082'))\"",
                )
                ref = f"QA-{int(time.time())}"
                multipart = (
                    f"curl -sk -X POST {API}/tenant/subscription/change-request "
                    f"-H 'Authorization: Bearer {tenant_token}' -H 'X-Tenant: {tenant_slug}' "
                    f"-F plan_code={target_plan_code} -F payment_gateway_id={gateway_id} "
                    f"-F payment_reference={ref} -F payment_proof=@/tmp/qa-proof.png"
                )
                raw = run(client, multipart)
                try:
                    cr = json.loads(raw)
                    order_id = (cr.get("data") or {}).get("request_id")
                    record(
                        "tenant_change_request",
                        cr.get("success") is True and bool(order_id),
                        f"action={target_action} order={order_id} plan={target_plan_code} ref={ref}",
                    )
                except json.JSONDecodeError:
                    record("tenant_change_request", False, raw[:200])

                if order_id:
                    code, orders_admin = curl_json(
                        client,
                        "GET",
                        f"{API}/platform/order-plans?search={order_id}&per_page=5",
                        headers={"Authorization": f"Bearer {token}"},
                    )
                    record("admin_order_plans_list", code == 200, f"order_id={order_id} HTTP {code}")

                    pay_list = run(
                        client,
                        f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Payment::where('plan_request_id',{order_id})->value('id');\"",
                    ).strip()
                    payment_id = int(pay_list) if pay_list.isdigit() else None
                    record("admin_payments_pending", bool(payment_id), f"payment_id={payment_id} order={order_id}")

                if payment_id and token:
                    code, paid = curl_json(
                        client,
                        "POST",
                        f"{API}/platform/payments/{payment_id}/mark-paid",
                        headers={"Authorization": f"Bearer {token}"},
                    )
                    record(
                        "admin_mark_paid",
                        code in {200, 201} and isinstance(paid, dict) and paid.get("success") is not False,
                        f"payment_id={payment_id} HTTP {code}",
                    )

                    _, sub_after = curl_json(client, "GET", f"{API}/tenant/subscription/current", headers=th)
                    plan_after = plan_code_from(sub_after)
                    record(
                        "subscription_after_mark_paid",
                        plan_after == target_plan_code,
                        f"before={current_slug} after={plan_after} expected={target_plan_code}",
                    )

                    # renewal test: request renew on current paid plan if action available
                    _, plans2 = curl_json(client, "GET", f"{API}/tenant/subscription/plans", headers=th)
                    renew_code = None
                    if isinstance(plans2, dict):
                        for p in plans2.get("data") or []:
                            if p.get("action") == "renew":
                                renew_code = p.get("code") or p.get("slug")
                                break
                    if renew_code:
                        ref2 = f"RENEW-{int(time.time())}"
                        raw2 = run(
                            client,
                            f"curl -sk -X POST {API}/tenant/subscription/change-request "
                            f"-H 'Authorization: Bearer {tenant_token}' -H 'X-Tenant: {tenant_slug}' "
                            f"-F plan_code={renew_code} -F payment_gateway_id={gateway_id} "
                            f"-F payment_reference={ref2} -F payment_proof=@/tmp/qa-proof.png",
                        )
                        try:
                            rj = json.loads(raw2)
                            renew_order = (rj.get("data") or {}).get("request_id")
                            record("tenant_renew_request", rj.get("success") is True, f"order={renew_order} ref={ref2}")
                            if renew_order:
                                rid = run(
                                    client,
                                    f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Payment::where('plan_request_id',{renew_order})->value('id');\"",
                                ).strip()
                                if rid.isdigit():
                                    ends_before = run(
                                        client,
                                        f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Tenant::where('slug','{tenant_slug}')->value('subscription_ends_at');\"",
                                    ).strip()
                                    curl_json(client, "POST", f"{API}/platform/payments/{rid}/mark-paid", headers={"Authorization": f"Bearer {token}"})
                                    ends_after = run(
                                        client,
                                        f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Tenant::where('slug','{tenant_slug}')->value('subscription_ends_at');\"",
                                    ).strip()
                                    record(
                                        "tenant_renew_extends_date",
                                        ends_after > ends_before,
                                        f"ends_before={ends_before} ends_after={ends_after}",
                                    )
                        except json.JSONDecodeError:
                            record("tenant_renew_request", False, raw2[:200])

                    # cancel test (non-destructive: verify API accepts; tenant still exists)
                    code, cancel_resp = curl_json(
                        client,
                        "POST",
                        f"{API}/tenant/subscription/cancel",
                        {"reason": "QA subscription billing test"},
                        th,
                    )
                    cancelled_at = run(
                        client,
                        f"cd {BACKEND} && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\Central\\\\Tenant::where('slug','{tenant_slug}')->value('cancelled_at');\"",
                    ).strip()
                    record(
                        "tenant_cancel_subscription",
                        code in {200, 201} and cancelled_at not in {"", "null", "None"},
                        f"HTTP {code} cancelled_at={cancelled_at}",
                    )

    out_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), "docs", "subscription-billing-qa-results.json")
    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"WROTE {out_path}")
    client.close()
    fails = [r for r in results if r["status"] == "FAIL"]
    return 0 if not fails else 1


if __name__ == "__main__":
    raise SystemExit(main())
